<?
$relPath="./../../pinc/";
include_once($relPath.'v_site.inc');
include_once($relPath.'dp_main.inc');
include_once($relPath.'theme.inc');
include_once($relPath.'projectinfo.inc');
include_once($relPath.'project_edit.inc');
$projectinfo = new projectinfo();
include_once('projectmgr.inc');
include_once('projectmgr_select.inc');

theme("Project Managers", "header");

abort_if_not_manager();
echo_manager_header();

?>
	<p><b>Project Manager Notice:</b><br>

	<p>
	The "Search for Projects" page is now the default PM page.

	<hr width='50%'>
	<p>
	The "Pages Left" column of the projects table has been removed.
	This should speed up assembly of the PM page dramatically,
	and also reduce the load on the server.
	Note that the "Pages Remaining to be Proofed" for a project
	is still available, on its Project Details page.

	<hr width='50%'>
	<p>
	Note the new <b>Search Your Projects</b> link above. Try it out!

	<hr width='50%'>
	<p>
	There is a new way to upload files to the site without needing anyone to help you:
	<ol>
		<li>FTP to <? echo $uploads_host; ?> with username <? echo $uploads_account; ?> and password <? echo $uploads_password; ?>
		<li>Make a directory named what the projectID is (look in the URL of the project when you click on the title, labeled project=projectIDXXX, use entire projectIDXXX for the folder)
		<li>Upload the text and images to that folder, titled 001.txt, 001.png, 002.txt, 002.png, etc...
		<li>Click on the title of the book to view it's details.
		<li>Click on the link at the top titled "Add Images And Text From <? echo $uploads_account; ?> Account".
		<li>It will load them into the database, look over the list of pages before setting it to "Waiting to be Released".
	</ol>
	<p>Recent bug fixes include being able to put any characters in the project comments box and preventing books from the same author to be available in the First Round (meaning if you have 4 volumes of a series, you can put them into Waiting to be Released and they will be released as each passes through the First Round).
	<p>If you want the projects to go to post-processing automatically, e-mail the <a href='mailto:<? echo $site_manager_email_addr; ?>'>site manager</A>.<br><br>
	<hr width="75%" align="center"><br>

<?
	if ( $_GET['show'] == 'search_form' || $_GET['show'] == '' ) {
		echo "
		    <center>
		    Search for projects matching the following criteria:<br>
		    <form method=get action='projectmgr.php'>
			<input type='hidden' name='show' value='search'>
			<table>
			<tr>
			    <td>Title</td>
			    <td><input type='text' name='title'></td>
			</tr>
			<tr>
			    <td>Author</td>
			    <td><input type='text' name='author'></td>
			</tr>
		";
        	if ($userP['sitemanager'] == "yes")
		{
			$sitemgrCheck = mysql_query("SELECT sitemanager FROM users WHERE username = '$pguser'");
			if (mysql_result($sitemgrCheck,0,"sitemanager") == "yes") {
		    		echo "
					<tr>
			    		<td>Project Manager</td>
			    		<td><input type='text' name='project_manager'></td>
					</tr>
		    		";
		    	}
		}
		// In the <select> tag, we set the name attribute to 'state[]'.
		// I'm pretty sure this doesn't mean anything to HTML/HTTP,
		// but PHP takes it as a cue to make the multiple values of
		// the select control available as an array.
		// That is, $_GET['state'] will be an array containing
		// all selected values.
		echo "
			<tr>
			    <td>State</td>
			    <td>
			    <select name='state[]' multiple>
				<option value=''>any state</option>
		";
		foreach ($PROJECT_STATES_IN_ORDER as $proj_state_in_order)
		{
		    echo "<option value='$proj_state_in_order'>";
		    echo project_states_text($proj_state_in_order);
		    echo "</option>\n";
		}
		echo "
			    </select>
			    </td>
			</tr>
			</table>
			<input type='submit' value='Search'>
		    </form>
		    Matching [except for State] is case-insensitive and unanchored;<br>
		    so, for instance, 'jim' matches both 'Jimmy Olsen' and 'piggyjimjams'.<br>
		    <br>
		    If desired, you should be able to select<br>
		    multiple values for State (e.g., by holding down Ctrl).
		    </center>
		";
	} else {
		$show_pages_left = 0;

		echo "<center><table border=1 width=630 cellpadding=0 cellspacing=0 style='border-collapse: collapse' bordercolor=#111111>";
    		echo "<tr>";
      		echo "<td width='175' align='center' bgcolor='".$theme['color_headerbar_bg']."'><font color='".$theme['color_headerbar_font']."'><b>Title</b></font></td>";
      		echo "<td width='100' align='center' bgcolor='".$theme['color_headerbar_bg']."'><font color='".$theme['color_headerbar_font']."'><b>Author</b></font></td>";
		if ( $show_pages_left )
		{
		    echo "<td width='50' align='center' bgcolor='".$theme['color_headerbar_bg']."'><font color='".$theme['color_headerbar_font']."'><b>Left</b></font></td>";
		}
      		echo "<td width='75' align='center' bgcolor='".$theme['color_headerbar_bg']."'><font color='".$theme['color_headerbar_font']."'><b>";
	        if ($_GET['show'] == "site") {
			echo "PM";
        	} else {
        		echo "Owner";
        	}
		echo "</b></font></td>";
      		echo "<td width='180' align='center' bgcolor='".$theme['color_headerbar_bg']."'><font color='".$theme['color_headerbar_font']."'><b>Project Status</b></font></td>";
      		echo "<td width='50' align='center' bgcolor='".$theme['color_headerbar_bg']."'><font color='".$theme['color_headerbar_font']."'><b>Options</b></font></td>";
      		echo "</tr>";

        	$numrows = 0;
        	if ($_GET['show'] == "site" && $userP['sitemanager'] == "yes") {
        		$sitemgrCheck = mysql_query("SELECT sitemanager FROM users WHERE username = '$pguser'");
			if (mysql_result($sitemgrCheck,0,"sitemanager") == "yes") {
				$condition = "state != '".PROJ_SUBMIT_PG_POSTED."'";
			} else {
				$condition = "username = '$pguser'";
			}
        	} elseif ($_GET['show'] == "all") {
			$condition = "username = '$pguser'";
		} elseif ($_GET['show'] == 'search') {
			$condition = '1';
			if ( $_GET['title'] != '' )
			{
			    $condition .= " AND nameofwork LIKE '%{$_GET['title']}%'";
			}
			if ( $_GET['author'] != '' )
			{
			    $condition .= " AND authorsname LIKE '%{$_GET['author']}%'";
			}
			if ($userP['sitemanager'] == "yes")
			{
				$sitemgrCheck = mysql_query("SELECT sitemanager FROM users WHERE username = '$pguser'");
				if (mysql_result($sitemgrCheck,0,"sitemanager") == "yes") {
			    		if ( $_GET['project_manager' ] != '' )
			    		{
						$condition .= " AND username LIKE '%{$_GET['project_manager']}%'";
			    		}
			    	} else {
			    		$condition .= " AND username='$pguser'";
			    	}
			}
			else
			{
			    // The user is a project manager, not a site admin,
			    // so they can only see their own projects.
			    $condition .= " AND username='$pguser'";
			}
			if ( count($_GET['state']) > 0 )
			{
			    $condition .= " AND (0";
			    foreach( $_GET['state'] as $state )
			    {
				if ( $state == '' )
				{
				    $condition .= " OR 1";
				}
				else
				{
				    $condition .= " OR state='$state'";
				}
			    }
			    $condition .= ")";
			}
        	} else {
			$condition = "state != '".PROJ_SUBMIT_PG_POSTED."' AND username = '$pguser'";
        	}
		$result = mysql_query("
			SELECT projectid, nameofwork, authorsname, checkedoutby, state, username
			FROM projects
			WHERE $condition
			ORDER BY nameofwork asc
		");
        	if ($result != "") $numrows = (mysql_num_rows($result));

		$tr_num = 0;
		foreach ($PROJECT_STATES_IN_ORDER as $proj_state_in_order)
		{
        	   $rownum = 0;
        	   while ($rownum < $numrows) {
            	     $state = mysql_result($result, $rownum, "state");
		     if ($state == $proj_state_in_order)
		     {
            		$name = mysql_result($result, $rownum, "nameofwork");
            		$author = mysql_result($result, $rownum, "authorsname");
            		$projectid = mysql_result($result, $rownum, "projectid");
            		$outby = mysql_result($result, $rownum, "checkedoutby");

			if ($tr_num % 2 ) {
                		$bgcolor = $theme['color_main_bg'];
                	} else {
                		$bgcolor = $theme['color_navbar_bg'];
            		}

            		echo "<tr bgcolor=$bgcolor>\n";

			// Title
			echo "<td><a href=\"project_detail.php?project=$projectid\">$name</a></td>\n";

			// Author
			echo "<td>$author</td>\n";

			// Left
			if ( $show_pages_left )
			{
			    $projectinfo->update_avail($projectid, $state);
			    echo "<td align=\"center\">$projectinfo->availablepages</td>\n";
			}

			// Owner
			echo "<td align=\"center\">";
            		if ($_GET['show'] == 'site') {
                		print mysql_result($result, $rownum, "username");
            		} else if ($outby != "") {
				// Maybe we should get this info via a
				// left outer join in the big select query.
                		$tempsql = mysql_query("SELECT email FROM users WHERE username = '$outby'");
                		$outbyemail = mysql_result($tempsql, 0, "email");
                		print "<a href=mailto:$outbyemail>$outby</a>";
            		}
			echo "</td>\n";

			// Project Status
			echo "
			    <td valign=center>
				<form
				    name='$projectid'
				    method='get'
				    action='changestate.php'>
				    <input
					type='hidden'
					name='project'
					value='$projectid'>
				    <select
					name='state'
					onchange='this.form.submit()'>
			";
            		getSelect($state);
            		echo "</select></form></td>\n";

			// Options
			echo "<td align=center>";
			print "<a href=\"editproject.php?project=$projectid\">Edit</a>";
            		if ($state==PROJ_POST_UNAVAILABLE || $state==PROJ_POST_AVAILABLE || $state==PROJ_POST_CHECKED_OUT) print " <a href = \"$projects_url/$projectid/$projectid.zip\">D/L</A>";
            		if (($state == PROJ_POST_VERIFYING) || ($state == PROJ_POST_COMPLETE)) print " <a href=\"$projects_url/$projectid/post.zip\">D/L</A>";
            		echo "</td>\n";

			echo "</tr>\n";

			$tr_num++;
		     }
		     $rownum++;
        	   }
		}
		echo "<tr><td colspan=6 bgcolor='".$theme['color_headerbar_bg']."'>&nbsp;</td></tr></table>";
	}

echo "<br>";
theme("","footer");
?>
