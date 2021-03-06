<?php
# Copyright (c) 2017, CESNET. All rights reserved.
#
# Redistribution and use in source and binary forms, with or
# without modification, are permitted provided that the following
# conditions are met:
#
#   o Redistributions of source code must retain the above
#     copyright notice, this list of conditions and the following
#     disclaimer.
#   o Redistributions in binary form must reproduce the above
#     copyright notice, this list of conditions and the following
#     disclaimer in the documentation and/or other materials
#     provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND
# CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
# MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS
# BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
# EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
# TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
# ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
# OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

/**
 * @author Michal Prochazka
 * @author Jakub Mlcak
 */
class HostDao {
  private $db;
  
  public function __construct(DbManager &$dbManager) {
    $this->db = $dbManager;  
  }
  
  public function create(Host &$host) {
    if ($host == null) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("Host object is not valid");
    }
    $this->db->query(
      "insert into Host set
      	hostname='".$this->db->escape($host->getHostname())."',
      	ip='".$this->db->escape($host->getIp())."',
      	reporterIp='".$this->db->escape($host->getReporterIp())."',
      	reporterHostname='".$this->db->escape($host->getReporterHostname())."',
      	kernel='".$this->db->escape($host->getKernel())."',
      	osId=".$this->db->escape($host->getOsId()).",
      	archId=".$this->db->escape($host->getArchId()).",
      	domainId=".$this->db->escape($host->getDomainId()).",
      	lastReportId=".($host->getLastReportId() == -1 ? "NULL" : $this->db->escape($host->getLastReportId())).",
      	type='".$this->db->escape($host->getType())."',
        ownRepositoriesDef=".$this->db->escape($host->getOwnRepositoriesDef()));
    
    # Set the newly assigned id
    $host->setId($this->db->getLastInsertedId());
    Utils::log(LOG_DEBUG, "Host created", __FILE__, __LINE__);
  }
  
  public function getIdByHostnameIpReporterHostnameReporterIp($hostname, $ip, $reporterHostname, $reporterIp) {
    $id = $this->db->queryToSingleValue("
      select id from Host
      where hostname='".$this->db->escape($hostname)."'
      and ip='".$this->db->escape($ip)."'
      and reporterHostname='".$this->db->escape($reporterHostname)."'
      and reporterIp='".$this->db->escape($reporterIp)."'
    ");
    if ($id == null) {
      return -1;
    }
    return $id;
  }
  
  public function getById($id, $userId = -1) {
    # Try to find the host in the DB
    if (!is_numeric($id)) return null;

    $select = "distinct
      Host.id as _id, 
      Host.hostname as _hostname,
      Host.ip as _ip, 
      Host.reporterIp as _reporterIp,
      Host.reporterHostname as _reporterHostname,
      Host.kernel as _kernel,
      Host.type as _type,
      Host.ownRepositoriesDef as _ownRepositoriesDef,
      Host.osId as _osId,
      Host.archId as _archId,
      Host.domainId as _domainId,
      Host.lastReportId as _lastReportId";
    $from = "Host";
    $join = null;
    $where[] = "Host.id = $id";

    if($userId != -1){
      $join[] ="inner join HostHostGroup on HostHostGroup.hostId = Host.id";
      $join[] ="left join UserHostGroup on HostHostGroup.hostGroupId = UserHostGroup.hostGroupId";
      $join[] ="left join UserHost on Host.id = UserHost.hostId";
      $where[] = "(UserHostGroup.userId = $userId or UserHost.userId = $userId)";
    }

    $sql = Utils::sqlSelectStatement($select, $from, $join, $where);

    return $this->db->queryObject($sql, "Host");
  }
  
  public function getByHostname($hostname) {
    $hostId = $this->db->queryToSingleValue("select id from Host where hostname='$hostname'");
    return $this->getById($hostId);  
  }
  
  public function getHostsIds($orderBy = null, $pageSize = -1, $pageNum = -1, $startsWith = null, $userId = -1, $directlyAssignedToUser = false) {

    $select = "distinct Host.id";
    $from = "Host";
    $join = null;
    $where = null;
    $order = null;
    $limit = null;
    $offset = null;

    // Because os and arch are ids to other tables, we have to do different sorting
    switch ($orderBy) {
      case "os":
        $join[] = "left join Os on Host.osId=Os.id";
        $order[] = "Os.name";
        break;
      case "arch":
        $join[] = "left join Arch on Host.archId=Arch.id";
        $order[] = "Arch.name";
        break;
      case "hostGroup":
        $join[] = "left join HostHostGroup as g on g.hostId = Host.id";
        $join[] = "left join HostGroup on g.hostGroupId = HostGroup.id";
        $order[] = "HostGroup.name";
        break;
      case null:
        $order[] = "Host.hostname";
        break;
      default:
        $order[] = "Host.".$this->db->escape($orderBy)."";
    }

    if($startsWith != null) {
      $where[] = "lower(hostname) like '".$this->db->escape(strtolower($startsWith))."%'";
    }

    if($userId != -1) {
      $join[] = "left join UserHost on Host.id = UserHost.hostId";

      if ($directlyAssignedToUser) {
          $where[] = "UserHost.userId = $userId";
      } else {
          $join[] = "inner join HostHostGroup on HostHostGroup.hostId = Host.id";
          $join[] = "left join UserHostGroup on HostHostGroup.hostGroupId = UserHostGroup.hostGroupId";
          $where[] = "(UserHostGroup.userId = $userId or UserHost.userId = $userId)";
      }
    }

    if ($pageSize != -1 && $pageNum != -1) {
      $limit = $pageSize;
      $offset = $pageSize * $pageNum;
    }

    $sql = Utils::sqlSelectStatement($select, $from, $join, $where, $order, $limit, $offset);

    return $this->db->queryToSingleValueMultiRow($sql);
  }


  public function update(Host &$host) {
    if ($host == null || $host->getId() == -1) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("Host object is not valid or Host.id is not set");
    }
    $dbHost = $this->getById($host->getId());
    if ($dbHost == null) {
      throw new Exception("Host cannot be retreived from the DB");
    }
    
    $entries = array();
    if ($host->getHostname() != $dbHost->getHostname()) {
      $entries['hostname'] = "'".$this->db->escape($host->getHostname())."'";
    }
    if ($host->getIp() != $dbHost->getIp()) {
      $entries['ip'] = "'".$this->db->escape($host->getIp())."'";
    }
    if ($host->getReporterHostname() != $dbHost->getReporterHostname()) {
      $entries['reporterHostname'] = "'".$this->db->escape($host->getReporterHostname())."'";
    }
    if ($host->getReporterIp() != $dbHost->getReporterIp()) {
      $entries['reporterIp'] = "'".$this->db->escape($host->getReporterIp())."'";
    }
    if ($host->getKernel() != $dbHost->getKernel()) {
      $entries['kernel'] ="'". $this->db->escape($host->getKernel())."'";
    }
    if ($host->getOsId() != $dbHost->getOsId()) {
      $entries['osId'] = $this->db->escape($host->getOsId());
    }
    if ($host->getArchId() != $dbHost->getArchId()) {
      $entries['archId'] = $this->db->escape($host->getArchId());
    }
    if ($host->getDomainId() != $dbHost->getDomainId()) {
      $entries['domainId'] = $this->db->escape($host->getDomainId());
    }
    if ($host->getType() != $dbHost->getType()) {
      $entries['type'] = "'".$this->db->escape($host->getType())."'";
    }
    if ($host->getOwnRepositoriesDef() != $dbHost->getOwnRepositoriesDef()) {
      $entries['ownRepositoriesDef'] = "'".$this->db->escape($host->getOwnRepositoriesDef())."'";
    }
    
    if (sizeof($entries) > 0) {
      # Construct SQL query
      $sql = "update Host set";
      $sqle = "";
      foreach ($entries as $column => $value) {
        $sqle .= " $column=$value,";
      }
      # Remove last comma
      $sqle = preg_replace('/(.*),$/', '\1', $sqle);
      
      $sql .= $sqle . " where id=".$host->getId();
      
      $this->db->query($sql);

      Utils::log(LOG_DEBUG, "Host updated", __FILE__, __LINE__);
    }
  }
  
  public function delete(Host &$host) {
    if ($host == null || $host->getId() == -1) {
      Utils::log(LOG_ERR, "Exception", __FILE__, __LINE__);
      throw new Exception("Host object is not valid or Host.id is not set");
    }
    $this->db->query(
      "delete from Host where id=".$host->getId());
    Utils::log(LOG_DEBUG, "Host deleted", __FILE__, __LINE__);
  }
  
  public function setLastReportId($hostId, $reportId) {
    $this->db->query("update Host set lastReportId=$reportId where id=$hostId");
  }
}
?>
