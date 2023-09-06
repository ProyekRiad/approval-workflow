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

class ApprovalHandler
{
  static string $EXC_USER_NOT_FOUND = 'exc_user_not_found';
  static string $EXC_FLOW_NOT_FOUND = 'exc_flow_not_found';
  static string $EXC_PERMISSION_DENIED = 'exc_permission_denied';
  static string $EXC_APPROVAL_NOT_RUNNING = 'exc_approval_not_running';
  static string $EXC_APPROVAL_NOT_REJECTED = 'exc_approval_not_rejected';

  var $db;

  public function __construct($db)
  {
    $this->db = $db;
  }

  public function start($flowType, int $userId, $parameters)
  {
    // CHECK APAKAH USER ADA
    $user = UserRepository::getById($this->db, $userId);
    if ($user == null)
      throw new Exception(ApprovalHandler::$EXC_USER_NOT_FOUND);

    // AMBIL DATA FLOW SESUAI $flowType
    $flow = FlowRepository::getByType($this->db, $flowType);
    if ($flow == null)
      throw new Exception(ApprovalHandler::$EXC_FLOW_NOT_FOUND);

    // INSERT DATA APPROVAL
    $approvalId = ApprovalRepository::insert($this->db, $flow['id'], $userId, $parameters);

    // BUAT HISTORY APPROVAL DIBUAT
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      $userId,
      'Permintaan persetujuan dibuat',
      HFLAG_CREATED,
      null,
      null
    );

    // PROSES KE STEP SELANJUTNYA
    $this->checkNextStep($approvalId);

    // KEMBALIKAN STATUS APPROVAL TERBARU
    return ApprovalRepository::getCurrentStatus($this->db, $approvalId);
  }

  public function approve($approvalId, $userId, $notes, $file)
  {
    // CHECK APAKAH USER ADA
    $user = UserRepository::getById($this->db, $userId);
    if ($user == null)
      throw new Exception(ApprovalHandler::$EXC_USER_NOT_FOUND);

    // VALIDASI PERMISSION APPROVER SESUAI $userId
    if (!ApprovalRepository::isUserHasPermission($this->db, $approvalId, $userId))
      throw new Exception(ApprovalHandler::$EXC_PERMISSION_DENIED);

    // AMBIL STATUS APPROVAL TERAKHIR
    $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // TOLAK JIKA DOKUMEN SUDAH CLOSE
    if ($data['status'] != 'ON_PROGRESS')
      throw new Exception(ApprovalHandler::$EXC_APPROVAL_NOT_RUNNING);

    // BUAT HISTORY APPROVAL
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      $userId,
      "Persetujuan pada tahap {$data['flow_step_name']} disetujui oleh {$user['name']}.",
      HFLAG_APPROVED,
      $notes,
      $file
    );

    // PROSES KE STEP SELANJUTNYA
    $this->checkNextStep($approvalId);

    // KEMBALIKAN STATUS APPROVAL TERBARU
    return ApprovalRepository::getCurrentStatus($this->db, $approvalId);
  }

  private function checkNextStep($approvalId)
  {
    // AMBIL DATA APPROVAL TERBARU
    $approval = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // AMBIL DAFTAR STEPS SESUAI FLOW ID
    $steps = FlowRepository::getStepsById($this->db, $approval['flow_id']);

    // LOOP STEPS UNTUK MENGAMBIL STEP SELANJUTNYA
    $currentStepId = $approval['flow_step_id'];
    $nextStep = null;
    if ($currentStepId == null) {
      $nextStep = count($steps) > 0 ? $steps[0] : null;
    } else {
      $currentStep = array_column($steps, null, 'id')[$currentStepId];
      foreach ($steps as $step) {
        if ($step['order'] > $currentStep['order']) {
          $condition = trim($step['condition']);

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
    }

    if ($nextStep != null) {
      // SIMPAN NEXT STEP
      ApprovalRepository::update($this->db, $approvalId, 'ON_PROGRESS', $nextStep['id']);

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
      // JIKA next step tidak ditemukan, berarti approval sudah selesai
      // TANDAI APPROVAL MENJADI APPROVED
      ApprovalRepository::update($this->db, $approvalId, 'APPROVED', null);

      // Jika next step null, berarti proses sudah selesai
      // Buat history bahwa sudah approval sudah selesai
      // sleep(1); // sleep supaya history bisa berurut jika ada history yang berbarengan
      ApprovalHistoryRepository::insert(
        $this->db,
        $approvalId,
        null,
        'Proses persetujuan selesai.',
        HFLAG_DONE,
        null,
        null
      );
    }
  }

  public function reject($approvalId, $userId, $notes, $file)
  {
    // CHECK APAKAH USER ADA
    $user = UserRepository::getById($this->db, $userId);
    if ($user == null)
      throw new Exception(ApprovalHandler::$EXC_USER_NOT_FOUND);

    // VALIDASI PERMISSION APPROVER SESUAI $userId
    if (!ApprovalRepository::isUserHasPermission($this->db, $approvalId, $userId))
      throw new Exception(ApprovalHandler::$EXC_PERMISSION_DENIED);

    // AMBIL STATUS APPROVAL TERAKHIR
    $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // TOLAK JIKA DOKUMEN SUDAH CLOSE
    if ($data['status'] != 'ON_PROGRESS')
      throw new Exception(ApprovalHandler::$EXC_APPROVAL_NOT_RUNNING);

    // UBAH STATUS APPROVAL MENJADI 'REJECTED'
    ApprovalRepository::update($this->db, $approvalId, 'REJECTED', null);

    // BUAT HISTORY APPROVAL DIREJECT
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      $userId,
      "Persetujuan pada tahap {$data['flow_step_name']} ditolak oleh {$user['name']}.",
      HFLAG_REJECTED,
      $notes,
      $file
    );

    // KEMBALIKAN STATUS APPROVAL TERBARU
    return ApprovalRepository::getCurrentStatus($this->db, $approvalId);
  }

  public function reset($approvalId, $userId, $notes, $file)
  {
    // AMBIL STATUS APPROVAL TERAKHIR
    $data = ApprovalRepository::getCurrentStatus($this->db, $approvalId);

    // TOLAK JIKA DOKUMEN BUKAN DOKUMEN REJECT
    if ($data['status'] != 'REJECTED')
      throw new Exception(ApprovalHandler::$EXC_APPROVAL_NOT_REJECTED);

    // UBAH STATUS APPROVAL MENJADI 'ON_PROGRESS'
    ApprovalRepository::update($this->db, $approvalId, 'ON_PROGRESS', null);

    // PROSES KE STEP SELANJUTNYA
    $this->checkNextStep($approvalId);

    // BUAT HISTORY APPROVAL DIREJECT
    ApprovalHistoryRepository::insert(
      $this->db,
      $approvalId,
      $userId,
      "Pengajuan ulang persetujuan dimulai",
      HFLAG_RESET,
      $notes,
      $file
    );

    // KEMBALIKAN STATUS APPROVAL TERBARU
    return ApprovalRepository::getCurrentStatus($this->db, $approvalId);
  }

  public function rebuildApprovers()
  {
    $approvals = ApprovalRepository::getRunningApprovals($this->db);

    foreach ($approvals as $approval) {
      // AMBIL DAFTAR APPROVER
      $approvers = FlowRepository::getStepUsers($this->db, $approval['flow_step_id'], $approval['parameters']);

      // SIMPAN APPROVER KE DATABASE, HAPUS DULU DAFTAR APPROVER SEBELUMNYA
      ApprovalRepository::assignApprovers($this->db, $approval['id'], $approvers);
    }
  }
}