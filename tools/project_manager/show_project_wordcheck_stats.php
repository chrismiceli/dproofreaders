<?php
$relPath = "./../../pinc/";
include_once($relPath.'base.inc');
include_once($relPath.'wordcheck_engine.inc');
include_once($relPath.'Project.inc');
include_once($relPath.'links.inc');
include_once($relPath.'theme.inc');
include_once($relPath.'graph_data.inc');
include_once($relPath.'post_files.inc');
include_once("./word_freq_table.inc");

require_login();

set_time_limit(0); // no time limit

$projectid = get_projectID_param($_GET, 'projectid');
$project = new Project($projectid);

enforce_edit_authorization($projectid);

$title = _("Project WordCheck Statistics");

// load bad words for project
$proj_bad_words = load_project_bad_words($projectid);

// load bad words for the site for this project
$site_bad_words = load_site_bad_words_given_project($projectid);

$pages_res = page_info_query($projectid, Rounds::get_last()->id, 'LE');

// get the entire text
$page_texts = get_page_texts($pages_res);
mysqli_free_result($pages_res);

// now run it through WordCheck
[$bad_words_w_freq, $languages, $messages] =
    get_bad_word_freqs_for_project_text($page_texts, $projectid, get_project_languages($projectid));

// see how many of the words are on the site and project bad word list
$total["proj_bad_words"] = 0;
$total["site_bad_words"] = 0;
$total["flagged"] = 0;
foreach ($bad_words_w_freq as $word => $freq) {
    // is this word in the project's Bad list?
    if (in_array($word, $proj_bad_words)) {
        $total["proj_bad_words"] += $freq;
    }

    // is this word in the site's Bad list?
    if (in_array($word, $site_bad_words)) {
        $total["site_bad_words"] += $freq;
    }

    // add total flagged words
    $total["flagged"] += $freq;
}
$total["num_pages"] = $project->n_pages;


// now run it again except we're going to count the words per page
// this time through
$pages_res = page_info_query($projectid, Rounds::get_last()->id, 'LE');
$page_stats = [];
// iterate through all the pages gathering stats
while ([$page_text, $page, $proofer_names] = page_info_fetch($pages_res)) {
    // find which words would be flagged for this page
    $page_words_w_freq = get_distinct_words_in_text($page_text);

    $page_stats[$page]["flagged"] = 0;
    // cycle through the words and count things
    foreach ($page_words_w_freq as $word => $freq) {
        // is this word flagged?
        if (isset($bad_words_w_freq[$word])) {
            $page_stats[$page]["flagged"] += $freq;
        }
    }
}
mysqli_free_result($pages_res);

$total["flagged_min"] = 1000000;
$total["flagged_max"] = 0;
$total["flagged_max_page"] = null;
$mode = [];
$graph_x = [];
$graph_y = [];
// we have per-page stats, lets aggregate them together
foreach ($page_stats as $page => $data) {
    $flagged = $data["flagged"];

    if ($total["flagged_max"] < $flagged) {
        $total["flagged_max"] = $flagged;
        $total["flagged_max_page"] = $page;
    }
    $total["flagged_min"] = min($total["flagged_min"], $flagged);
    @$mode[$flagged]++;

    // push data into our graph data
    array_push($graph_x, $page);
    array_push($graph_y, $flagged);
}

$graph_flags_per_page = [
    "title" => _("Flagged words per page"),
    "downloadLabel" => _("Download Chart"),
    "data" => [
        _("Flags") => [
            "x" => $graph_x,
            "y" => $graph_y,
        ],
    ],
];

// calculate the mode by reverse sorting the array, resetting
// the internal pointer, and using the first element
arsort($mode);
reset($mode);
$total["flagged_mode"] = key($mode);
$total["flagged_mode_num"] = $mode[$total["flagged_mode"]];

// initialize for empty projects
$flags_n_pages = [];

// use the $mode array to prepare the graph_pages_per_number_of_flags data
for ($numFlags = $total["flagged_min"]; $numFlags <= $total["flagged_max"]; $numFlags++) {
    if (isset($mode[$numFlags])) {
        $flags_n_pages[$numFlags] = $mode[$numFlags];
    } else {
        $flags_n_pages[$numFlags] = 0;
    }
}

$graph_pages_per_number_of_flags = [
    "title" => _("Number of flags on a page"),
    "downloadLabel" => _("Download Chart"),
    "data" => [
        _("Pages with that many flags") => [
            "x" => array_keys($flags_n_pages),
            "y" => array_values($flags_n_pages),
        ],
    ],
];

$graphs = [
    ["barLineGraph", "graph_flags_per_page", $graph_flags_per_page],
    ["barLineGraph", "graph_pages_per_number_of_flags", $graph_pages_per_number_of_flags],
];

output_header($title, NO_STATSBAR, [
    "js_files" => get_graph_js_files(),
    "js_data" => build_svg_graph_inits($graphs),
]);


echo "<h1>$title</h1>";
echo "<h2>" . get_project_name($projectid) . "</h2>";

echo "<p>" . _("The following statistics are generated from the most recently saved text of each page and the site and project's Good and Bad Word Lists.") . "</p>";

// calculate averages
$total["flagged_avg"] = $total["proj_bad_words_avg"] = $total["site_bad_words_avg"] = 0;
if ($total["num_pages"] > 0) {
    $total["flagged_avg"] = $total["flagged"] / $total["num_pages"];
    $total["proj_bad_words_avg"] = $total["proj_bad_words"] / $total["num_pages"];
    $total["site_bad_words_avg"] = $total["site_bad_words"] / $total["num_pages"];
} else {
    $total["flagged_min"] = 0;
}

?>
<table class='basic'>
<tr>
    <th class='label'><?php echo _("Number of pages in project"); ?></th>
    <td style='text-align: right;'><?php echo $total["num_pages"]; ?></td>
</tr>
<tr>
    <th class='label'><?php echo _("Number of flagged words in project"); ?></th>
    <td style='text-align: right;'><?php echo $total["flagged"]; ?></td>
</tr>
<tr>
    <th class='label'><?php echo _("Number of flagged words from site's Bad Word List"); ?></th>
    <td style='text-align: right;'><?php echo $total["site_bad_words"]; ?></td>
</tr>
<tr>
    <th class='label'><?php echo _("Number of flagged words from project's Bad Word List"); ?></th>
    <td style='text-align: right;'><?php echo $total["proj_bad_words"]; ?></td>
</tr>
<tr>
    <th class='label'><?php echo _("Mean flagged words per page"); ?></th>
    <td style='text-align: right;'><?php echo number_format($total["flagged_avg"], 3); ?></td>
</tr>
<tr>
    <th class='label'><?php echo _("Mean of words from site's Bad Word List flagged per page"); ?></th>
    <td style='text-align: right;'><?php echo number_format($total["site_bad_words_avg"], 3); ?></td>
</tr>
<tr>
    <th class='label'><?php echo _("Mean of words from project's Bad Word List flagged per page"); ?></th>
    <td style='text-align: right;'><?php echo number_format($total["proj_bad_words_avg"], 3); ?></td>
</tr>
<?php if (!is_null($total["flagged_max_page"])) { ?>
<tr>
    <th class='label'><?php echo _("Maximum flagged words per page"); ?></th>
    <td style='text-align: right;'><?php echo $total["flagged_max"]; ?> on <?php echo new_window_link("../page_browser.php?project=$projectid&amp;imagefile=" . $total["flagged_max_page"], $total["flagged_max_page"]); ?></a></td>
</tr>
<?php } // !is_null flagged_max_page?>
<tr>
    <th class='label'><?php echo _("Minimum flagged words per page"); ?></th>
    <td style='text-align: right;'><?php echo $total["flagged_min"]; ?></td>
</tr>
<tr>
    <th class='label'><?php echo _("Mode of flagged words per page"); ?></th>
    <td style='text-align: right;'><?php echo $total["flagged_mode"] . " (" . $total["flagged_mode_num"] . ")"; ?></td>
</tr>
</table>

<h2><?php echo _("Flagged words distribution"); ?></h2>

<p><div id='graph_flags_per_page' style='max-width: 640px;'></div></p>

<p><div id="graph_pages_per_number_of_flags" style='max-width: 640px;'></div></p>

<?php
