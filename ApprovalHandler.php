<?php

namespace Idemas\ApprovalWorkflow;

use Exception;
use Idemas\ApprovalWorkflow\Repository\ApprovalHistoryRepository;
use Idemas\ApprovalWorkflow\Repository\ApprovalRepository;
use Idemas\ApprovalWorkflow\Repository\FlowRepository;
use Idemas\ApprovalWorkflow\Repository\UserRepository;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

use const Idemas\ApprovalWorkflow\Repository\HFLAG_APPROVED;
use const Idemas\ApprovalWorkflow\Repository\HFLAG_CREATED;
use const Idemas\ApprovalWorkflow\Repository\HFLAG_DONE;
use const Idemas\ApprovalWorkflow\Repository\HFLAG_REJECTED;
use const Idemas\ApprovalWorkflow\Repository\HFLAG_SKIP;
use const Idemas\ApprovalWorkflow\Repository\HFLAG_RESET;
use const Idemas\ApprovalWorkflow\Repository\HFLAG_SYSTEM_REJECTED;

class ApprovalHandler
{
  static string $EXC_USER_NOT_FOUND = 'exc_user_not_found';
  static string $EXC_FLOW_NOT_FOUND = 'exc_flow_not_found';
  static string $EXC_PERMISSION_DENIED = 'exc_permission_denied';
  static string $EXC_APPROVAL_NOT_RUNNING = 'exc_approval_not_running';
  static string $EXC_APPROVAL_NOT_REJECTED = 'exc_approval_not_rejected';

  var $db;
  var $companyId;

  public function __construct($db, $companyId)
  {
    $this->db = $db;
    $this->companyId = $companyId;
  }

  /**
   * Fungsi ini digunakan untuk menginisiasi approval
   * 
   * $flowType : Identifier flow
   * $userId : ID user, user ini akan menjadi owner dari approval yang berjalan
   * $parameters : 
   * Parameters bisa diisi dengan data (array) bebas sesuai dengan kebutuhan. Tapi ada
   * Field-field default sebagai berikut :
   * - departmentId : Wajib diisi jika di flow menggunakan step user dengan type SYSTEM_GROUP -> department-manager atau department-head
   * - overrideManagerUserId : Bisa diisi dengan user id untuk meng-override approver department-manager. System akan mengabaikan 
   *   konfigurasi flow dan akan menggunakan user id yang diberikan sebagai approver.
   * - overrideHeadUserId : Bisa diisi dengan user id untuk meng-override approver department-head. System akan mengabaikan 
   *   konfigurasi flow dan akan menggunakan user id yang diberikan sebagai approver.
   * - assetCategoryId : Wajib diisi jika di flow menggunakan step user dengan type SYSTEM_GROUP -> asset-coordinator
   * - originAssetUserId : Wajib diisi jika di flow menggunakan step user dengan type SYSTEM_GROUP -> origin-asset-user
   *   diisi dengan id user dari employee yang bersangkutan
   * - destinationAssetUserId : Wajib diisi jika di flow menggunakan step user dengan type SYSTEM_GROUP -> destination-asset-user
   *   diisi dengan id user dari employee yang bersangkutan
   */
  public function start($flowType, int $userId, $parameters)
  {
    // CHECK APAKAH USER ADA
    $user = UserRepository::getById($this->db, $userId);
    if ($user == null)
      throw new Exception(ApprovalHandler::$EXC_USER_NOT_FOUND);

    // AMBIL DATA FLOW SESUAI $flowType
    $flow = FlowRepository::getByType($this->db, $this->companyId, $flowType);
    if ($flow == null)
      throw new Exception(ApprovalHandler::$EXC_FLOW_NOT_FOUND);

    // INSERT DATA APPROVAL
    $approvalId = ApprovalRepository::insert($this->db, $this->companyId, $flow['id'], $userId, $parameters);

    // BUAT HISTORY APPROVAL DIBUAT
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      null,
      $userId,
      'Permintaan persetujuan dibuat',
      HFLAG_CREATED,
      null,
      null
    );

    // PROSES KE STEP SELANJUTNYA
    $previousApprovers = ApprovalRepository::getCurrentApprovers($this->db, $approvalId);
    if ($flow['is_active'] != 0) {
      $this->checkNextStep($approvalId);
    } else {
      // UBAH STATUS APPROVAL MENJADI 'APPROVED'
      ApprovalRepository::update($this->db, $approvalId, 'APPROVED', null, null);

      // BUAT HISTORY APPROVAL DIANGGAP SELESAI KARENAN NON AKTIF
      ApprovalHistoryRepository::insert(
        $this->db,
        $approvalId,
        null,
        $userId,
        "Persetujuan dianggap selesai karena flow persetujuan NON-AKTIF.",
        HFLAG_DONE,
        null,
        null
      );
    }
    $nextApprovers = ApprovalRepository::getCurrentApprovers($this->db, $approvalId);

    // KEMBALIKAN STATUS APPROVAL TERBARU
    $tmp = ApprovalRepository::getCurrentStatus($this->db, $approvalId);
    $tmp['stakeholders']['owner'] = ApprovalRepository::getOwner($this->db, $approvalId);
    $tmp['stakeholders']['previousApprovers'] = $previousApprovers;
    $tmp['stakeholders']['currentApprovers'] = $nextApprovers;
    return $tmp;
  }

  /**
   * Fungsi ini digunakan untuk menyetujui tahapan yang sedang aktif
   * 
   * $approvalId : ID approval yang didapatkan saat menginisiasi approval
   * $userId : ID user yang melakukan approval
   * $notes : Catatan approval (opsional)
   * $file : Berkas pendukung (opsional)
   */
  public function approve($approvalId, $userId, $notes, $file)
  {
    // CHECK APAKAH USER ADA
    $user = UserRepository::getById($this->db, $userId);
    if ($user == null)
      throw new Exception(ApprovalHandler::$EXC_USER_NOT_FOUND);

    // AMBIL STATUS APPROVAL TERAKHIR
    $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // TOLAK JIKA DOKUMEN SUDAH CLOSE
    if ($data['status'] != 'ON_PROGRESS')
      throw new Exception(ApprovalHandler::$EXC_APPROVAL_NOT_RUNNING);

    // VALIDASI PERMISSION APPROVER SESUAI $userId
    if (!ApprovalRepository::isUserHasPermission($this->db, $approvalId, $userId))
      throw new Exception(ApprovalHandler::$EXC_PERMISSION_DENIED);

    // BUAT HISTORY APPROVAL DISETUJUI
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      $data['flow_step_id'],
      $userId,
      "Persetujuan pada tahap {$data['flow_step_name']} disetujui oleh {$user['name']}.",
      HFLAG_APPROVED,
      $notes,
      $file
    );

    // PROSES KE STEP SELANJUTNYA
    $previousApprovers = ApprovalRepository::getCurrentApprovers($this->db, $approvalId);
    $this->checkNextStep($approvalId);
    $nextApprovers = ApprovalRepository::getCurrentApprovers($this->db, $approvalId);

    // KEMBALIKAN STATUS APPROVAL TERBARU
    $tmp = ApprovalRepository::getCurrentStatus($this->db, $approvalId);
    $tmp['stakeholders']['owner'] = ApprovalRepository::getOwner($this->db, $approvalId);
    $tmp['stakeholders']['previousApprovers'] = $previousApprovers;
    $tmp['stakeholders']['currentApprovers'] = $nextApprovers;
    if (empty($nextApprovers)) {
      $tmp['stakeholders']['steps'] = $this->getAllStepInfo($approvalId);
    }
    return $tmp;
  }

  private function checkNextStep($approvalId)
  {
    // AMBIL DATA APPROVAL TERBARU
    $approval = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // AMBIL DAFTAR STEPS SESUAI FLOW ID
    $steps = FlowRepository::getStepsById($this->db, $approval['flow_id']);

    // LOOP STEPS UNTUK MENGAMBIL STEP SELANJUTNYA
    $currentStepId = $approval['flow_step_id'];
    $currentStep = $currentStepId ? array_column($steps, null, 'id')[$currentStepId] : null;
    $currentStepOrder = $currentStep ? $currentStep['order'] : -1;
    $nextStep = null;
    foreach ($steps as $step) {
      if ($step['order'] > $currentStepOrder) {
        $condition = trim($step['condition'] ?? '');

        // Jika kondisi tidak diisi, maka langsung gunakan step sebagai next step
        if (is_null($condition) || $condition == '') {
          $nextStep = $step;
          break;
        }

        // Check kondisi, jika tidak memenuhi maka skip
        $expressionLanguage = new ExpressionLanguage();
        $r = $expressionLanguage->evaluate($condition, $approval['parameters']);
        if (is_bool($r) && $r == true) {
          $nextStep = $step;
          break;
        }
      }
    }

    if ($nextStep != null) {
      // SIMPAN NEXT STEP
      ApprovalRepository::update($this->db, $approvalId, 'ON_PROGRESS', $nextStep['id'], null);

      // AMBIL DAFTAR APPROVER JIKA $nextStep TIDAK NULL
      $approvers = FlowRepository::getStepUsers($this->db, $nextStep['id'], $approval['parameters']);

      // SIMPAN APPROVER KE DATABASE, HAPUS DULU DAFTAR APPROVER SEBELUMNYA
      ApprovalRepository::assignApprovers($this->db, $approvalId, $approvers);

      if (count($approvers) <= 0) {
        // BUAT HISTORY INFORMASI STEP DILEWATI
        // sleep(1); // sleep supaya history bisa berurut jika ada history yang berbarengan
        ApprovalHistoryRepository::insert(
          $this->db,
          $approvalId,
          $nextStep['id'],
          null,
          "Proses {$nextStep['name']} dilewati karena tidak ada pemberi persetujuan di tahap ini.",
          HFLAG_SKIP,
          null,
          null
        );

        // LEWATI STEP JIKA TIDAK ADA APPROVER
        $this->checkNextStep($approvalId);
      }
    } else {
      // REMOVE PREVIOUS APPROVER
      ApprovalRepository::assignApprovers($this->db, $approvalId, []);

      // JIKA next step tidak ditemukan, berarti approval sudah selesai
      // TANDAI APPROVAL MENJADI APPROVED
      ApprovalRepository::update($this->db, $approvalId, 'APPROVED', null, null);

      // Jika next step null, berarti proses sudah selesai
      // Buat history bahwa sudah approval sudah selesai
      // sleep(1); // sleep supaya history bisa berurut jika ada history yang berbarengan
      ApprovalHistoryRepository::insert(
        $this->db,
        $approvalId,
        null,
        null,
        'Proses persetujuan selesai.',
        HFLAG_DONE,
        null,
        null
      );
    }
  }

  public function getAllStepInfo($approvalId): array
  {
    // AMBIL DATA APPROVAL TERBARU
    $approval = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // AMBIL DAFTAR STEPS SESUAI FLOW ID
    $steps = FlowRepository::getStepsById($this->db, $approval['flow_id']);

    $filteredSteps = array();
    foreach ($steps as $step) {
      $condition = trim($step['condition'] ?? '');

      // Jika kondisi tidak diisi, maka langsung gunakan step sebagai next step
      if (is_null($condition) || $condition == '') {
        array_push($filteredSteps, $step);
        continue;
      }

      // Check kondisi, jika tidak memenuhi maka skip
      $expressionLanguage = new ExpressionLanguage();
      $r = $expressionLanguage->evaluate($condition, $approval['parameters']);
      if (is_bool($r) && $r == true) {
        array_push($filteredSteps, $step);
        continue;
      }
    }

    // Loop untuk ambil daftar approver
    for ($i = 0; $i < count($filteredSteps); $i++) {
      $filteredSteps[$i]['approvers'] = FlowRepository::getStepUsers($this->db, $step['id'], $approval['parameters']);
    }

    return $filteredSteps;
  }

  /**
   * Fungsi ini digunakan untuk menolak tahapan yang sedang aktif
   * 
   * $approvalId : ID approval yang didapatkan saat menginisiasi approval
   * $userId : ID user yang melakukan approval
   * $notes : Catatan approval (opsional)
   * $file : Berkas pendukung (opsional)
   */
  public function reject($approvalId, $userId, $notes, $file)
  {
    // CHECK APAKAH USER ADA
    $user = UserRepository::getById($this->db, $userId);
    if ($user == null)
      throw new Exception(ApprovalHandler::$EXC_USER_NOT_FOUND);

    // AMBIL STATUS APPROVAL TERAKHIR
    $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // TOLAK JIKA DOKUMEN SUDAH CLOSE
    if ($data['status'] != 'ON_PROGRESS')
      throw new Exception(ApprovalHandler::$EXC_APPROVAL_NOT_RUNNING);

    // VALIDASI PERMISSION APPROVER SESUAI $userId
    if (!ApprovalRepository::isUserHasPermission($this->db, $approvalId, $userId))
      throw new Exception(ApprovalHandler::$EXC_PERMISSION_DENIED);

    // UBAH STATUS APPROVAL MENJADI 'REJECTED'
    ApprovalRepository::update($this->db, $approvalId, 'REJECTED', null, null);

    // BUAT HISTORY APPROVAL DIREJECT
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      $data['flow_step_id'],
      $userId,
      "Persetujuan pada tahap {$data['flow_step_name']} ditolak oleh {$user['name']}.",
      HFLAG_REJECTED,
      $notes,
      $file
    );

    $previousApprovers = ApprovalRepository::getCurrentApprovers($this->db, $approvalId);
    $nextApprovers = null;

    // KEMBALIKAN STATUS APPROVAL TERBARU
    $tmp = ApprovalRepository::getCurrentStatus($this->db, $approvalId);
    $tmp['stakeholders']['owner'] = ApprovalRepository::getOwner($this->db, $approvalId);
    $tmp['stakeholders']['previousApprovers'] = $previousApprovers;
    $tmp['stakeholders']['currentApprovers'] = $nextApprovers;
    return $tmp;
  }

  /**
   * Fungsi ini digunakan untuk menolak/mereset approval di status apa pun,
   * termasuk status COMPLETE.
   * Action akan tersimpan di dalam log, dengan subjek utamanya adalah Sistem
   * dan user yang tercatat di log adalah user yang memiliki tanggungjawab
   * atas terjadinya action ini.
   * 
   * Contoh kasus penggunaannya adalah, jika ada penolakan di salah satu approval
   * yang menjadi bagian dari sebuah group approval maka semua approval
   * di dalam group tersebut akan direject/direset.
   * 
   * $approvalId : ID approval yang didapatkan saat menginisiasi approval
   * $relatedUserId : ID user yang mentrigger penyebab reject by system. Siapa pun bisa 
   * menjadi penyebab action ini dieksekusi. Jadi tidak ada pengecekan role approver.
   * $notes : Catatan approval (opsional)
   * $file : Berkas pendukung (opsional)
   * $parameters : Data parameters baru (opsional, jika tidak diisi maka akan menggunakan parameter sebelumnya)
   */
  public function rejectBySystem($approvalId, $relatedUserId, $notes, $file)
  {
    // CHECK APAKAH USER ADA
    $user = UserRepository::getById($this->db, $relatedUserId);
    if ($user == null)
      throw new Exception(ApprovalHandler::$EXC_USER_NOT_FOUND);

    // AMBIL STATUS APPROVAL TERAKHIR
    $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // UBAH STATUS APPROVAL MENJADI 'REJECTED'
    ApprovalRepository::update($this->db, $approvalId, 'REJECTED', null, null);

    // BUAT HISTORY APPROVAL DIREJECT
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      $data['flow_step_id'],
      $relatedUserId,
      "Persetujuan ditolak dan direset oleh System.",
      HFLAG_SYSTEM_REJECTED,
      $notes,
      $file
    );

    $previousApprovers = ApprovalRepository::getCurrentApprovers($this->db, $approvalId);
    $nextApprovers = null;

    // KEMBALIKAN STATUS APPROVAL TERBARU
    $tmp = ApprovalRepository::getCurrentStatus($this->db, $approvalId);
    $tmp['stakeholders']['owner'] = ApprovalRepository::getOwner($this->db, $approvalId);
    $tmp['stakeholders']['previousApprovers'] = $previousApprovers;
    $tmp['stakeholders']['currentApprovers'] = $nextApprovers;
    $tmp['stakeholders']['steps'] = $this->getAllStepInfo($approvalId);
    return $tmp;
  }

  /**
   * Fungsi ini digunakan untuk mereset status approval yang REJECTED (ditolak) menjadi APPROVED. 
   * Approval akan dimulai kembali dari tahapan pertama.
   * 
   * $approvalId : ID approval yang didapatkan saat menginisiasi approval
   * $userId : ID user yang melakukan approval
   * $notes : Catatan approval (opsional)
   * $file : Berkas pendukung (opsional)
   */
  public function reset($approvalId, $userId, $notes, $file, $parameters)
  {
    // NOTE: Didisable karena ada kebutuhan meskipun sudah complete, bisa di-reset lagi
    // - AMBIL STATUS APPROVAL TERAKHIR
    // $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);
    // - TOLAK JIKA DOKUMEN BUKAN DOKUMEN REJECT
    // if ($data['status'] != 'REJECTED')
    //   throw new Exception(ApprovalHandler::$EXC_APPROVAL_NOT_REJECTED);

    // UBAH STATUS APPROVAL MENJADI 'ON_PROGRESS'
    ApprovalRepository::update($this->db, $approvalId, 'ON_PROGRESS', null, $parameters);

    // PROSES KE STEP SELANJUTNYA
    $this->checkNextStep($approvalId);

    // BUAT HISTORY APPROVAL DIMULAI KEMBALI
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      null,
      $userId,
      "Pengajuan ulang persetujuan dimulai",
      HFLAG_RESET,
      $notes,
      $file
    );

    $previousApprovers = null;
    $nextApprovers = ApprovalRepository::getCurrentApprovers($this->db, $approvalId);

    // KEMBALIKAN STATUS APPROVAL TERBARU
    $tmp = ApprovalRepository::getCurrentStatus($this->db, $approvalId);
    $tmp['stakeholders']['owner'] = ApprovalRepository::getOwner($this->db, $approvalId);
    $tmp['stakeholders']['previousApprovers'] = $previousApprovers;
    $tmp['stakeholders']['currentApprovers'] = $nextApprovers;
    return $tmp;
  }

  public function rebuildApprovers()
  {
    $approvals = ApprovalRepository::getRunningApprovals($this->db, $this->companyId);

    foreach ($approvals as $approval) {
      // AMBIL DAFTAR APPROVER
      $approvers = FlowRepository::getStepUsers($this->db, $approval['flow_step_id'], $approval['parameters']);

      // SIMPAN APPROVER KE DATABASE, HAPUS DULU DAFTAR APPROVER SEBELUMNYA
      ApprovalRepository::assignApprovers($this->db, $approval['id'], $approvers);
    }
  }

  public function getNextSteps($approvalId): mixed
  {
    $currentStatus = ApprovalRepository::getCurrentStatus($this->db, $approvalId);
    $steps = $this->getAllStepInfo($approvalId);

    $foundCurrent = false;
    foreach ($steps as $step) {
      if ($foundCurrent)
        return $step;
      if ($step['id'] === $currentStatus['flow_step_id'])
        $foundCurrent = true;
    }

    return null;
  }

  /**
   * Fungsi ini digunakan untuk mengambil path approval dari awal hingga selesai berikut dengan
   * status approval, approver, info step berjalan, dan tahapan approval selanjutnya (yang belum dieksekusi).
   * 
   * $approvalId : ID approval yang didapatkan saat menginisiasi approval
   */
  public function getApprovalPath($approvalId): array
  {
    // AMBIL STATUS APPROVAL TERAKHIR
    $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // AMBIL DAFTAR HISTORY YANG MENGGAMBARKAN 1 KELOMPOK TAHAPAN APPROVAL TERBARU 
    $lastHistories = [];
    foreach (ApprovalHistoryRepository::getAllByApprovalId($this->db, $approvalId) as $history) {
      if (in_array($history['flag'], [HFLAG_APPROVED, HFLAG_REJECTED, HFLAG_SYSTEM_REJECTED]))
        array_push($lastHistories, $history);

      if (in_array($history['flag'], [HFLAG_CREATED, HFLAG_RESET]))
        $lastHistories = [];
    }

    // AMBIL DATA STEP APPROVAL DARI AWAL SAMPAI AKHIR
    $approvalSteps = $this->getAllStepInfo($approvalId);


    // MENENTUKAN INDEX CURRENT STEP
    $tmp = [];
    $currentStepIndex = -1;
    $ct = 0;
    foreach ($approvalSteps as $approvalStep) {
      if ($approvalStep['id'] == $data['flow_step_id']) {
        $currentStepIndex = $ct;
      }

      array_push($tmp, $approvalStep);

      $ct++;
    }
    $approvalSteps = $tmp;

    // MENGATUR TIPE STEP DAN MEMADUKAN DENGAN HISTORY 
    $tmp = [];
    $ct = 0;
    foreach ($approvalSteps as $approvalStep) {
      $approvalStep['type'] = 'unknown';
      $approvalStep['approver_id'] = null;
      $approvalStep['approver_email'] = null;
      $approvalStep['approver_username'] = null;
      $approvalStep['approver_name'] = null;
      $approvalStep['approval_notes'] = null;
      $approvalStep['approval_file'] = null;
      $approvalStep['approval_time'] = null;

      // SET STEP TYPE
      if ($ct < $currentStepIndex)
        $approvalStep['type'] = 'passed';
      if ($ct == $currentStepIndex)
        $approvalStep['type'] = 'current';
      if ($ct > $currentStepIndex)
        $approvalStep['type'] = 'incoming';

      // PADUKAN DENGAN DATA HISTORY  (UNTUK MENGUBAH TYPE passed MENJADI YANG SEHARUSNYA SESUAI HISTORY)
      $filteredHistories = array_filter($lastHistories, function ($item) use ($approvalStep) {
        return $item['flow_step_id'] == $approvalStep['id'];
      });
      $history = null;
      if (count($filteredHistories) > 0)
        $history = array_values($filteredHistories)[0];

      if ($history) {
        $approvalStep['type'] = $history['flag'];
      }

      array_push($tmp, $approvalStep);

      $ct++;
    }
    $approvalSteps = $tmp;

    // MEMADUKAN DATA HISTORY DENGAN DATA STEP YANG DIOLAH SEBELUMNYA
    // - MAAP DATA HISTORY
    $hList = array_map(function ($item) {
      return [
        'id' => $item['flow_step_id'],
        'order' => $item['flow_step_order'],
        'flow_id' => $item['flow_step_flow_id'],
        'name' => $item['flow_step_name'],
        'condition' => $item['flow_step_condition'],
        'approvers' => [],
        'type' => $item['flag'],
        'approver_id' => $item['user_id'],
        'approver_email' => $item['user_email'],
        'approver_username' => $item['user_username'],
        'approver_name' => $item['user_name'],
        'approval_notes' => $item['notes'],
        'approval_file' => $item['file'],
        'approval_time' => $item['date_time'],
      ];
    }, $lastHistories);
    // - BUANG DATA STEP SELAIN incoming DAN current KARENA AKAN DIGANTI DENGAN DATA $hList 
    $cnList = array_filter($approvalSteps, function ($item) {
      return in_array($item['type'], ['incoming', 'current']);
    });

    // MERGE DATA AKHIR HISTORY DAN DATA AKHIR STEP
    // TAPI, JIKA STATUS DATA SUDAH APPROVED DATA STEP incoming DAN current DIHAPUS SAJA
    // KARENA BISA JADI STEP TERSEBUT DITAMBAHKAN KETIKA APPROVAL SUDAH APPROVED
    if ($data['status'] == 'APPROVED') {
      $tmp = $hList;
    } else {
      $tmp = array_merge($hList, array_values($cnList));
    }

    return $tmp;
  }

  /**
   * Fungsi ini digunakan untuk mengambil history approval.
   * 
   * $approvalId : ID approval yang didapatkan saat menginisiasi approval
   */
  public function getApprovalHistories($approvalId): array
  {
    return ApprovalHistoryRepository::getAllByApprovalId($this->db, $approvalId);
  }
}