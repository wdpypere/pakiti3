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

require(realpath(dirname(__FILE__)) . '/../../../common/Loader.php');
require(realpath(dirname(__FILE__)) . '/../Html.php');

// Instantiate the HTML module
$html = new HtmlModule($pakiti);

// Access control
$html->checkPermission("cveExceptions");

$entries = Utils::getHttpPostVar("entries");
if ($entries == "") {
    $entries = 0;
}

$exp_id = Utils::getHttpPostVar("exp_id");
if ($exp_id != "") {
    $exp = $pakiti->getManager("CveExceptionsManager")->getCveExceptionById($exp_id);
    $pakiti->getManager("CveExceptionsManager")->removeCveException($exp);
}

$selectedCveName = Utils::getHttpGetVar("cve-name");
$cveExceptions = array();

$cveNames = $pakiti->getManager("CveDefsManager")->getCveNames();
$osGroups = $pakiti->getManager("OsGroupsManager")->getOsGroups();

if ($entries > 0) {
    for ($i = 0; $i < $entries; $i++) {
        if (Utils::getHttpPostVar("exception$i") !== null) {
            $data = explode(' ', Utils::getHttpPostVar("exception$i"));
            # data[0] - pkgId
            # data[1] - osGroupId
            $exception = new CveException();
            $exception->setCveName($selectedCveName);
            $exception->setPkgId($data[0]);
            $exception->setOsGroupId($data[1]);
            $exception->setReason(Utils::getHttpPostVar("reason$i"));
            $exception->setModifier("");
            $pakiti->getManager("CveExceptionsManager")->storeCveException($exception);
        }
    }
}

if ($selectedCveName != "") {
    $cveExceptions = $pakiti->getManager("CveExceptionsManager")->getCveExceptionsByCveName($selectedCveName);
} else {
    $cveExceptions = $pakiti->getManager("CveExceptionsManager")->getCvesExceptions();
}

$html->printHeader(); ?>
    <form action="" method="get" name="cve_form">
        <p style="width:200px; margin:0 auto;">
            <label for="cve-name">CVE: </label>
            <select name="cve-name" onchange="cve_form.submit();">
                <?php

                print "<option value=\"\"";

                if ($selectedCveName == "") print " selected";
                print ">No Cve selected" . "</option>";

                foreach ($cveNames as $cveName) {
                    print "<option value=\"" . $cveName . "\"";
                    if ($selectedCveName == $cveName) print " selected";
                    print ">" . $cveName . "</option>\n";
                } ?>
            </select>

        </p>
    </form>
    <label><?php if($selectedCveName != ""){ print "Selected CVE:"; } else { print "All CVE Exceptions"; }; ?>
            <span style="font-weight: bold;">
            <?php print $selectedCveName ?>
        </span>
    </label>
    <form action="" method="post" name="exception_form">
        <table class="tableDetail">
            <tr>
                <th class="header">CVE</th>
                <th class="header">Package</th>
                <th class="header">Reason</th>
                <th class="header">Modifier</th>
                <th class="header">Timestamp</th>
                <th class="header">Action</th>
            </tr>
            <?php
            foreach ($cveExceptions as $cveException) {
                    $pkg = $pakiti->getManager("PkgsManager")->getPkgById($cveException->getPkgId());
                    $osGroup = $pakiti->getManager("OsGroupsManager")->getOsGroupById($cveException->getOsGroupId());
                    print "<tr>";
                    print "<td>" . $cveException->getCveName() . "</td>";
                    print "<td>" . $pkg->getName() . " " . $pkg->getVersionRelease() . "/ " . "<i>" . "(" . $pkg->getArch() . ") [" . $pkg->getType() . "] " . "</i> " . $osGroup->getName() . "</td>";
                    print "<td>" . $cveException->getReason() . "</td>";
                    print "<td>" . $cveException->getModifier() . "</td>";
                    print "<td>" . $cveException->getTimestamp() . "</td>";
                print "<td><span style='color: #002BFF; font-weight: bold; cursor: pointer;' name=\"remove\" value=\"" . $cveException->getId() . "\" onclick=\"document.getElementById('exp_id').value=" . $cveException->getId() . "; exception_form.submit()\" ><a>[remove]</a></span> </td>";
                    print "</tr>";
                }
            ?>
        </table>

        <?php
         if ($selectedCveName != ""){
        ?>
        <br><br>
        <table class="tableDetail">
            <tr>
                <th class="headerCheckBox"></th>
                <th class="header">Installed versions</th>
                <th class="header">Reason</th>
            </tr>
            <?php
            $i = 0;
            foreach ($osGroups as $osGroup) {
                $pkgs = $pakiti->getManager("PkgsManager")->getPkgsByCveNameAndOsGroup($selectedCveName, $osGroup);
                foreach ($pkgs as $pkg) {
                    print "<tr>
            <td>
                <input name=\"exception" . $i . "\" id=\"exception" . $i . "\" value=\"" . $pkg->getId() . " " . $osGroup->getId() . "\" type=\"checkbox\">
            </td>
            <td>
                " . $pkg->getName() . " " . $pkg->getVersionRelease() . "/ " . "<i>" . "(" . $pkg->getArch() . ") [" . $pkg->getType() . "] " . "</i> " . $osGroup->getName() . "
            </td>
            <td>
                <input type=\"text\" name=\"reason" . $i . "\" size=\"50\" onKeyUp=\"document.getElementById('exception" . $i . "').checked = true\">

            </td></tr>";
                    $i++;
                }
            }
            print "<td><input type=\"hidden\" name=\"entries\" value=\"" . $i . "\"></td>
            </table>";
            print "<input type=\"hidden\" id=\"exp_id\" name=\"exp_id\" value=\"\">";
            print "<input type=\"hidden\" id=\"cve-name\" name=\"cve-name\" value=\"" . $selectedCveName . "\">";
            ?>
            <button type="button" onclick="exception_form.submit();">Save changes</button>
        <?php
        }
        ?>
    </form>

<?php $html->printFooter(); ?>
