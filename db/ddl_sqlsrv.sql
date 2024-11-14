-- wf_department_users definition
CREATE TABLE wf_department_users (
  id INT IDENTITY(1, 1) NOT NULL,
  department_id INT NOT NULL,
  user_id INT NOT NULL,
  job_level VARCHAR(10) CHECK (job_level IN ('STAFF', 'MANAGER', 'HEAD')) NOT NULL,
  company_id INT NOT NULL,
  PRIMARY KEY (id)
);

-- wf_asset_coordinator_users definition
CREATE TABLE wf_asset_coordinator_users (
  id INT IDENTITY(1, 1) NOT NULL,
  asset_category_id INT NOT NULL,
  user_id INT NOT NULL,
  company_id INT NOT NULL,
  PRIMARY KEY (id)
);

-- wf_approver_groups definition
CREATE TABLE wf_approver_groups (
  id INT IDENTITY(1, 1) NOT NULL,
  name VARCHAR(255) NULL,
  company_id INT NOT NULL,
  PRIMARY KEY (id)
);

-- wf_approver_group_users definition
CREATE TABLE wf_approver_group_users (
  id INT IDENTITY(1, 1) NOT NULL,
  approver_group_id INT NOT NULL,
  user_id INT NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT FK_wf_approver_group_users FOREIGN KEY (approver_group_id) REFERENCES wf_approver_groups(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- wf_flows definition
CREATE TABLE wf_flows (
  id INT IDENTITY(1, 1) NOT NULL,
  type VARCHAR(2) CHECK (type IN ('PR', 'PO')) NOT NULL,
  company_id INT NOT NULL,
  is_active INT NOT NULL DEFAULT 0,
  label VARCHAR(100) NULL,
  PRIMARY KEY (id)
);

-- wf_flow_steps definition
CREATE TABLE wf_flow_steps (
  id INT IDENTITY(1, 1) NOT NULL,
  [order] INT NOT NULL,
  -- [order] karena 'order' adalah kata kunci SQL Server
  flow_id INT NOT NULL,
  name VARCHAR(100) NULL,
  [condition] VARCHAR(1000) NULL,
  -- [condition] karena 'condition' adalah kata kunci SQL Server
  PRIMARY KEY (id),
  CONSTRAINT FK_wf_flow_steps FOREIGN KEY (flow_id) REFERENCES wf_flows(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- wf_flow_step_approvers definition
CREATE TABLE wf_flow_step_approvers (
  id INT IDENTITY(1, 1) NOT NULL,
  flow_step_id INT NOT NULL,
  [type] VARCHAR(20) CHECK ([type] IN ('USER', 'GROUP', 'SYSTEM_GROUP')) NOT NULL,
  data VARCHAR(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT FK_wf_flow_step_approvers FOREIGN KEY (flow_step_id) REFERENCES wf_flow_steps(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- wf_approvals definition
CREATE TABLE wf_approvals (
  id INT IDENTITY(1, 1) NOT NULL,
  flow_id INT NOT NULL,
  [status] VARCHAR(20) CHECK (
    [status] IN ('ON_PROGRESS', 'APPROVED', 'REJECTED')
  ) NOT NULL,
  flow_step_id INT NULL,
  user_id INT NOT NULL,
  parameters VARCHAR(1000) NULL,
  company_id INT NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT FK_wf_approvals FOREIGN KEY (flow_id) REFERENCES wf_flows(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_wf_approvals_flow_step FOREIGN KEY (flow_step_id) REFERENCES wf_flow_steps(id)
);

-- wf_approval_active_users definition
CREATE TABLE wf_approval_active_users (
  id INT IDENTITY(1, 1) NOT NULL,
  approval_id INT NOT NULL,
  user_id INT NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT FK_wf_approval_active_users FOREIGN KEY (approval_id) REFERENCES wf_approvals(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- wf_approval_histories definition
CREATE TABLE wf_approval_histories (
  id INT IDENTITY(1, 1) NOT NULL,
  approval_id INT NOT NULL,
  user_id INT NULL,
  flow_step_id INT NULL,
  title VARCHAR(100) NULL,
  flag VARCHAR(100) NULL,
  notes VARCHAR(100) NULL,
  [file] VARCHAR(100) NULL,
  date_time INT NULL,
  PRIMARY KEY (id),
  CONSTRAINT FK_wf_approval_histories FOREIGN KEY (approval_id) REFERENCES wf_approvals(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_wf_approval_histories_flow_step FOREIGN KEY (flow_step_id) REFERENCES wf_flow_steps(id) ON UPDATE CASCADE
);