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
 */
class PkgDao
{
    private $db;

    public function __construct(DbManager &$dbManager)
    {
        $this->db = $dbManager;
    }

    /*******************
     * Public functions
     *******************/

    /*
     * Stores the pkg in the DB
     */
    public function create(Pkg &$pkg)
    {
        $this->db->query(
            "insert into Pkg set
          name='" . $this->db->escape($pkg->getName()) . "',
          version='" . $this->db->escape($pkg->getVersion()) . "',
          arch='" . $this->db->escape($pkg->getArch()) . "',
          type='" . $this->db->escape($pkg->getType()) . "',
          `release`='" . $this->db->escape($pkg->getRelease()) . "'");

        # Set the newly assigned id
        $pkg->setId($this->db->getLastInsertedId());
    }

    /*
     * Get the pkg by name, version, release and arch
     */
    public function getPkg($name, $version, $release, $arch, $type)
    {
        return $this->db->queryObject(
            "select
    		id as _id, name as _name, version as _version, `release` as _release, arch as _arch, type as _type
      from
      	Pkg
      where
      	binary name='" . $this->db->escape($name) . "' AND
        version='" . $this->db->escape($version) . "' AND
        type='" . $this->db->escape($type) . "' AND
        `release`='" . $this->db->escape($release) . "' AND
        arch='" . $this->db->escape($arch) . "'", "Pkg");
    }

    /*
    * Get the pkg by its ID
    */
    public function getById($id)
    {
        if (!is_numeric($id)) return null;
        return $this->getBy($id, "id");
    }

    /*
     * Get all pkgs
     * returns array of pkgs
     */
    public function getAllPkgs()
    {
        return $this->db->queryObjects(
            "select id as _id, name as _name, version as _version, arch as _arch, type as _type, `release` as _release from Pkg"
            , "Pkg");
    }

    /*
     * Get the pkgs by their IDs
     * $ids is array of IDs
     * returns array of pkgs
     */
    public function getPkgsByPkgsIds($pkgsIds)
    {
        if(empty($pkgsIds)){
            return array();
        }

        $sql = "select id as _id, name as _name, version as _version, arch as _arch, type as _type, `release` as _release
            from Pkg
            where id IN (" . implode(",", array_map("intval", $pkgsIds)) . ")";

        return $this->db->queryObjects($sql, "Pkg");
    }

    /*
     * Get the pkg by its name
     */
    public function getByName($name)
    {
        return $this->getBy($name, "name");
    }

    public function getPkgIdByNameVersionReleaseArchType($pkgName, $pkgVersion, $pkgRelease, $pkgArch, $pkgType)
    {
        $sql = "select id from Pkg where binary
        name='" . $this->db->escape($pkgName) . "' and
        version='" . $this->db->escape($pkgVersion) . "' and
      	arch='" . $this->db->escape($pkgArch) . "' and
        type='" . $this->db->escape($pkgType) . "' and
      	`release`='" . $this->db->escape($pkgRelease) . "'";
        $id = $this->db->queryToSingleValue($sql);

        if ($id == null) {
            return -1;
        }
        return $id;
    }

    /*
     * Update the pkg in the DB
     */
    public function update(Pkg &$pkg)
    {
        $this->db->query(
            "update Pkg set
      	name='" . $this->db->escape($pkg->getName()) . "',
      	version='" . $this->db->escape($pkg->getVersion()) . "',
      	arch='" . $this->db->escape($pkg->getArch()) . "',
      	type='" . $this->db->escape($pkg->getType()) . "',
      	`release`='" . $this->db->escape($pkg->getRelease()) . "'
      where id=" . $this->db->escape($pkg->getId()));
    }

    /*
     * Delete the pkg from the DB
     */
    public function delete(Pkg &$pkg)
    {
        $this->db->query(
            "delete from Pkg where id=" . $this->db->escape($pkg->getId()));
    }

    /*********************
     * Protected functins
     *********************/

    /*
     * We can get the data by ID or name
     */
    protected function getBy($value, $type)
    {
        $where = "";
        if ($type == "id") {
            $where = "id=" . $this->db->escape($value);
        } else if ($type == "name") {
            $where = "binary name='" . $this->db->escape($value) . "'";
        } else {
            throw new Exception("Undefined type of the getBy");
        }
        return $this->db->queryObject(
            "select
    		id as _id, name as _name, version as _version, arch as _arch, type as _type, `release` as _release
      from 
      	Pkg 
      where
      	$where"
            , "Pkg");
    }

    public function getPkgsTypesNames(){
        $sql = "select distinct(type) from Pkg";
        return $this->db->queryToSingleValueMultiRow($sql);
    }

}
?>
