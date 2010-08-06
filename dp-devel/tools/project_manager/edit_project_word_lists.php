<?php
$relPath="./../../pinc/";
include_once($relPath.'site_vars.php');
include_once($relPath.'dp_main.inc');
include_once($relPath.'theme.inc');
include_once($relPath.'links.inc');
include_once('edit_common.inc');
include_once($relPath.'wordcheck_engine.inc');
include_once($relPath.'metarefresh.inc');
include_once($relPath.'project_edit.inc');
include_once($relPath.'Project.inc');
include_once($relPath.'misc.inc');  // attr_safe()

$return = array_get($_REQUEST,"return","$code_url/tools/project_manager/projectmgr.php");

if ( !user_is_PM() )
{
    die('permission denied');
}

$pwlh = new ProjectWordListHolder;

$errors = array();
$good_word_conflict = $bad_word_conflict = false;

if (isset($_POST['saveAndProject']) || isset($_POST['saveAndPM']) || isset($_POST['save']) )
{
    $errors = $pwlh->set_from_post();
    if (count($errors)==0)
    {
        list($good_word_conflict,$bad_word_conflict,$errors) = $pwlh->save_to_files();
        if (count($errors)==0)
        {
            if (isset($_POST['saveAndProject']))
            {
                metarefresh(0, "$code_url/project.php?id=$pwlh->projectid", _("Save and Go To Project"), "");
                exit;
            }
            elseif (isset($_POST['saveAndPM']))
            {
                metarefresh(0, "$code_url/tools/project_manager/projectmgr.php", _("Save and Go To PM Page"), "");
                exit;
            }
        }
        elseif (isset($_POST['save']))
        {
            // No errors, but fall through.
        }
    }
    else
    {
        // Errors.
        // fall through
    }
} elseif(isset($_POST['quit'])) {
    // if return is empty for whatever reason take them to
    // the PM page
    if(empty($return))
        $return="$code_url/tools/project_manager/projectmgr.php";

    // do the redirect
    metarefresh(0, $return, _("Quit without Saving"), "");
    exit;
} elseif(isset($_POST['reload'])) {
    // fall through
}

$pwlh->set_from_db();

$pwlh->set_from_files(!$good_word_conflict,!$bad_word_conflict);

$page_title = _("Edit Project Word Lists");

$no_stats=1;
theme($page_title, "header");
echo "<h1>$page_title</h1>\n";

foreach($errors as $error) {
    echo "<p class='error'>$error</p>";
}

$pwlh->show_form();

theme("", "footer");

// XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX

class ProjectWordListHolder
{
    // load data from database
    function set_from_db()
    {
        $projectid = validate_projectID('projectid', @$_REQUEST['projectid']);

        $ucep_result = user_can_edit_project($projectid);
        // we only let people clone projects that they can edit, so this
        // is valid whether they are cloning or editing
        if ( $ucep_result == PROJECT_DOES_NOT_EXIST )
        {
            return array(_("parameter 'projectid' is invalid: no such project").": '$projectid'");
        }
        else if ( $ucep_result == USER_CANNOT_EDIT_PROJECT )
        {
            return array(_("you are not allowed to edit this project").": '$projectid'");
        }
        else if ( $ucep_result == USER_CAN_EDIT_PROJECT )
        {
            // fine
        }
        else
        {
            return array(_("unexpected return value from user_can_edit_project") . ": '$ucep_result'");
        }

        $res = mysql_query("SELECT nameofwork, username, authorsname, language, checkedoutby, state FROM projects WHERE projectid = '$projectid'");
        if (mysql_num_rows($res) == 0)
        {
            return array(_("parameter 'projectid' is invalid") . ": '$projectid'");
        }

        $ar = mysql_fetch_array($res);

        $this->projectid        = $projectid;
        $this->nameofwork       = $ar['nameofwork'];
        $this->projectmanager   = $ar['username'];
        $this->authorsname      = $ar['authorsname'];
        $this->language         = $ar['language'];
        $this->checkedoutby     = $ar['checkedoutby'];
        $this->state            = $ar['state'];

        mysql_free_result($res);

        return array();
    }

    function set_from_files($load_good_words=true, $load_bad_words=true)
    {
        $errors = array();

        if($load_good_words) {
            $gwl_object = get_project_word_file($this->projectid,"good");
            $this->gwl_timestamp = $gwl_object->mod_time;
            $good_words = load_project_good_words($this->projectid);

            if ( is_string($good_words) )
            {
                array_push($errors,$good_words);
                $this->good_words = '';
            }
            else
            {
                $this->good_words = implode("\n", $good_words);
            }
        }

        if($load_bad_words) {
            $bwl_object = get_project_word_file($this->projectid,"bad");
            $this->bwl_timestamp = $bwl_object->mod_time;
            $bad_words=load_project_bad_words($this->projectid);
            if ( is_string($bad_words) )
            {
                array_push($errors,$bad_words);
                $this->bad_words = '';
            }
            else
            {
                $this->bad_words = implode("\n", $bad_words);
            }
        }

        return $errors;
    }

    function set_from_post()
    {
        if ( get_magic_quotes_gpc() )
        {
            // Values in $_POST come with backslashes added.
            // We want the fields of $this to be unescaped strings,
            // so we strip the slashes.
            $_POST = array_map('stripslashes', $_POST);
        }

        if ( isset($_POST['projectid']) )
        {
            $this->projectid = validate_projectID('projectid', @$_POST['projectid']);

            $ucep_result = user_can_edit_project($this->projectid);
            if ( $ucep_result == PROJECT_DOES_NOT_EXIST )
            {
                return array(_("parameter 'projectid' is invalid: no such project").": '$this->projectid'");
            }
            else if ( $ucep_result == USER_CANNOT_EDIT_PROJECT )
            {
                return array(_("you are not allowed to edit this project").": '$this->projectid'");
            }
            else if ( $ucep_result == USER_CAN_EDIT_PROJECT )
            {
                // fine
            }
            else
            {
                return array(_("unexpected return value from user_can_edit_project") . ": '$ucep_result'");
            }
        }

        $this->projectid        = validate_projectID('projectid', @$_POST['projectid']);
        $this->good_words       = @$_POST['good_words'];
        $this->bad_words        = @$_POST['bad_words'];
        $this->gwl_timestamp    = get_integer_param($_POST, 'gwl_timestamp', null, null, null);
        $this->bwl_timestamp    = get_integer_param($_POST, 'bwl_timestamp', null, null, null);

        return array();
    }


    // -------------------------------------------------------------------------

    function save_to_files()
    {
        global $projects_dir, $uploads_dir, $pguser;

        $good_word_conflict = $bad_word_conflict = false;
        $messages = array();

        // first, check to see if the good or bad word list
        // has changed out from beneath us
        $gwl_object = get_project_word_file($this->projectid,"good");
        $bwl_object = get_project_word_file($this->projectid,"bad");
        $current_gwl_timestamp = $gwl_object->mod_time;
        $current_bwl_timestamp = $bwl_object->mod_time;

        if($current_gwl_timestamp != $this->gwl_timestamp) {
            // TRANSLATORS: %s is a link to the Good Word List
            $error = sprintf(_("The Good Words List was changed by another process during your edit session. Your changes to this list have not been saved to prevent data loss. View the %s and merge your changes manually. If you want the superset of both lists, simply append the contents of the Good Words List to that within the Good Words edit box below - the server will remove any duplicates. Saving this page again will override this message."),new_window_link($gwl_object->abs_url,_("Good Words List")));
            $this->gwl_timestamp = $current_gwl_timestamp;
            array_push($messages,$error);
            $good_word_conflict = true;
        } else {
            // everything looks good, save the changes
            $good_words = explode("[lf]",str_replace(array("\r","\n"),array('',"[lf]"),$this->good_words));
            save_project_good_words($this->projectid, $good_words);
        }

        if($current_bwl_timestamp != $this->bwl_timestamp) {
            // TRANSLATORS: %s is a link to the Bad Word List
            $error = sprintf(_("The Bad Words List was changed by another process during your edit session. Your changes to this list have not been saved to prevent data loss. View the %s and merge your changes manually. If you want the superset of both lists, simply append the contents of the Bad Words List to that within the Bad Words edit box below - the server will remove any duplicates. Saving this page again will override this message."),new_window_link($bwl_object->abs_url,_("Bad Words List")));
            $this->bwl_timestamp = $current_bwl_timestamp;
            array_push($messages,$error);
            $bad_word_conflict = true;
        } else {
            // everything looks good, save the changes
            $bad_words = explode("[lf]",str_replace(array("\r","\n"),array('',"[lf]"),$this->bad_words));
            save_project_bad_words($this->projectid, $bad_words);
        }

        
        return array($good_word_conflict,$bad_word_conflict,$messages);
    }

    // =========================================================================

    function show_form()
    {
        global $theme;

        $this->echo_stylesheet();

        echo "<form method='post' enctype='multipart/form-data' action='". htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ."'>";

        $this->show_hidden_controls();

        echo "<center>";
        echo "<table class='wordlisttable'>";

        $this->show_visible_controls();

        // The space between buttons ensures that very long (translated) button
        // labels do not force the display to be wider than the screen.
        echo "<tr>";
        echo   "<td class='label' colspan='2' align='center' style='padding: 0.5em;'>";
        echo     "<input type='submit' name='saveAndPM' value='", attr_safe(_("Save and Go To PM Page")), "'> ";
        echo     "<input type='submit' name='saveAndProject' value='", attr_safe(_("Save and Go To Project")), "'> ";
        echo     "<input type='submit' name='save' value='", attr_safe(_("Save")), "'> ";
        echo     "<input type='submit' name='quit' value='", attr_safe(_("Quit Without Saving")), "'> ";
        echo     "<input type='submit' name='reload' value='", attr_safe(_("Refresh Word Lists")), "'>";
        echo   "</td>";
        echo "</tr>\n";

    echo "<tr>";
    echo "<td class='label'>" . _("Project Information") . "</td>";
    echo "<td>" . new_window_link( "editproject.php?action=edit&amp;project=$this->projectid", _("Edit project information") ) . "</td>";
    echo "</tr>";

        echo "</table>";
        echo "</center>";
        echo "</form>";
        echo "\n";
    }

    // -------------------------------------------------------------------------

    function echo_stylesheet() {
?>
    <style type="text/css">
    table.wordlisttable {
        padding: 5px;
        border-collapse: collapse;
        width: 90%;
    }
    table.wordlisttable td {
        border: thin solid #000;
    }
    table.wordlisttable td.label {
        background-color: #CCC;
        font-weight: bold;
    }
    table.wordlisttable .mono { font-family: monospace; }
    table.wordlisttable textarea { width: 100%; }
    p.error { color: red; }
    </style>
<?php
    }

    function show_hidden_controls()
    {
        global $return;

        echo "<input type='hidden' name='projectid' value='$this->projectid'>";
        echo "<input type='hidden' name='gwl_timestamp' value='$this->gwl_timestamp'>";
        echo "<input type='hidden' name='bwl_timestamp' value='$this->bwl_timestamp'>";
        echo "<input type='hidden' name='return' value='$return'>";
    }


    function show_visible_controls() {
        $goodWordData = encodeFormValue($this->good_words);
        $badWordData = encodeFormValue($this->bad_words);

        $fields=array(
            "projectid" => _("Project ID"),
            "nameofwork" => _("Name of Work"),
            "authorsname" => _("Author's Name"),
            "projectmanager" => _("Project Manager"),
            "checkedoutby" => _("Post-Processor"),
            "language" => _("Language")
        );

        foreach($fields as $field => $label) {
            echo "<tr>";
            echo "<td class='label'>$label</td>";
            echo "<td>" . $this->$field . "</td>";
            echo "</tr>";
        }

        $exist_OCR_pages = ($this->number_of_pages_in_round(null) > 0);
        $exist_pages_in_P1_or_later = ($this->number_of_pages_in_round(get_Round_for_round_number(1)) > 0);

        // due to some special circumstances, not all projects may have pages in P1
        // so we'll check to see if the project is in a state after P1 and count
        // that as just as good
        if(!$exist_pages_in_P1_or_later) {
            $current_project_round = get_Round_for_project_state($this->state);
            if($current_project_round && $current_project_round->round_number > 1)
                $exist_pages_in_P1_or_later = true;
        }

        // if the project doesn't have any OCR pages and the project
        // has no P1 or later pages, report a message. The second criteria
        // is important for type-in projects that may have no OCR pages but
        // will have P1 or later pages
        if(!$exist_OCR_pages && !$exist_pages_in_P1_or_later) {
            echo "<tr>";
            echo "<td colspan='2'>";
            echo "<p class='error' style='text-align: center;'>";
            echo _("There are no pages associated with this project.");
            echo "</p>";
            echo "</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td class='label' style='text-align: center;' colspan='2'>";
            echo _("WordCheck Tools and Reports");
            echo "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td class='label'>" . _("Ad Hoc Word Details") . "</td>";
            echo "<td>" . new_window_link("show_adhoc_word_details.php?projectid=$this->projectid",_("Show details for ad hoc words")) . "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td class='label'>" . _("WordCheck Statistics") . "</td>";
            echo "<td>" . new_window_link("show_project_wordcheck_stats.php?projectid=$this->projectid",_("Show WordCheck flagged word statistics")) . "</td>";
            echo "</tr>";

            if($exist_pages_in_P1_or_later) {
                echo "<tr>";
                echo "<td class='label'>" . _("WordCheck Usage") . "</td>";
                echo "<td>" . new_window_link("show_project_wordcheck_usage.php?projectid=$this->projectid",_("Show WordCheck interface usage")) . "</td>";
                echo "</tr>";
            }

            echo "<tr>";
            echo "<td class='label' style='text-align: center;' colspan='2'>";
            echo _("Word List Suggestion Tools");
            echo "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td colspan='2'>";
    
            echo "<table width='100%'>";

            echo "<tr>";
            echo "<td style='width: 50%; text-align: center;' valign='top'>";

            echo "<p>";
            echo "<b>" . _("Words that WordCheck would currently flag:") . "</b><br>";
            echo new_window_link(
                "show_current_flagged_words.php?projectid=$this->projectid",
                _("Display")
            );
            echo " | ";
            echo "<a href='show_current_flagged_words.php?projectid=$this->projectid&amp;format=file'>" . _("Download") . "</a>";
            echo "</p>";

            $suggestions = load_project_good_word_suggestions($this->projectid);
            if (count($suggestions))
            {
                echo "<p>";
                echo "<b>" . _("Suggestions from proofreaders:") . "</b><br>";
                echo new_window_link(
                    "show_good_word_suggestions.php?projectid=$this->projectid",
                    _("Display")
                );
                echo " | ";
                echo "<a href='show_good_word_suggestions.php?projectid=$this->projectid&amp;timeCutoff=0&amp;format=file'>" . _("Download") . "</a>";
                echo "</p>";
            }

            echo "</td>";
            echo "<td style='width: 50%; text-align: center;' valign='top'>";

            // see if the site has Possible Bad Word files
            $possible_bad_words = load_site_possible_bad_words_given_project($this->projectid);

            if(count($possible_bad_words)) {
                echo "<p>";
                echo "<b>" . _("Words in the Site's Possible bad words file:") . "</b><br>";
                echo new_window_link(
                    "show_project_possible_bad_words.php?projectid=$this->projectid",
                    _("Display")
                );
                echo " | ";
                echo "<a href='show_project_possible_bad_words.php?projectid=$this->projectid&amp;format=file'>" . _("Download") . "</a>";
                echo "</p>";
            }

            // see if there are P1 (or later) and OCR pages before showing the link.
            // type-in projects may have P1 (or later) pages but no OCR pages
            // and the current show_project_stealth_scannos.php page only works
            // with projects that have OCR text
            if($exist_pages_in_P1_or_later && $exist_OCR_pages) {
                echo "<p>";
                echo "<b>" . _("Suggestions from diff analysis:") . "</b><br>";
                echo new_window_link(
                    "show_project_stealth_scannos.php?projectid=$this->projectid",
                    _("Display")
                );
                echo " | ";
                echo "<a href='show_project_stealth_scannos.php?projectid=$this->projectid&amp;format=file'>" . _("Download") . "</a>";
                echo "</p>";
            }

            echo "</td>";
            echo "</tr>";
            echo "</table>";

            echo "</td>";
            echo "</tr>";
        }

        echo "<tr>";
        echo "<td class='label' style='text-align: center;' colspan='2'>";
        echo _("Project Dictionary - Word Lists");
        echo "</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td colspan='2'>";

        echo "<table width='100%'>";
        echo "<tr>";
        echo "<td class='label' style='text-align: center;'>" . _("Good Words") . "</td>";
        echo "<td class='label' style='text-align: center;'>" . _("Bad Words") . "</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td style='width: 50%;'>";
        echo "<textarea class='mono' name='good_words' cols='40' rows='20'>$goodWordData</textarea>";
        echo "</td>";

        echo "<td style='width: 50%;'>";
        echo "<textarea class='mono' name='bad_words' cols='40' rows='20'>$badWordData</textarea>";
        echo "</td>";
        echo "</tr>";
        echo "</table>";

        echo "</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td colspan='2' style='text-align: center;'>";

        echo sprintf(
            // TRANSLATORS: %s is a link to the WordCheck FAQ.
            _("See the %s for more information on word lists."),
            new_window_link( "../../faq/wordcheck-faq.php", _("WordCheck FAQ") )
        );

        echo "</td>";
        echo "</tr>";
    }


    function number_of_pages_in_round($round)
    {
        if($round !== null) {
            $round_column=$round->text_column_name;
        } else {
            $round_column="master_text";
        }
        $sql = "select count(*) from $this->projectid where $round_column <> ''";

        $res = mysql_query($sql);
        if ($res === FALSE)
        {
            return 0;
        }

        $count = mysql_fetch_row($res);

        mysql_free_result($res);

        return $count[0];
    }

}

// vim: sw=4 ts=4 expandtab
?>
