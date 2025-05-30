<?php

class ProjectUtils extends PHPUnit\Framework\TestCase
{
    protected $TEST_USERNAME_PM = "ProjectTestPM_php";
    protected $TEST_USERNAME = "ProjectTest_php";
    protected $TEST_OLDUSERNAME = "ProjectTest_old_php";
    protected $TEST_IMAGESOURCE = "PrjTest";
    protected $valid_projectID = "projectID45c225f598e32";
    protected $valid_page_image = "001.png";
    protected $valid_project_data = [
        "nameofwork" => "War and Peace",
        "authorsname" => "Bob Smith",
        "language" => "English",
        "genre" => "Other",
        "difficulty" => "average",
        "clearance" => "123abc",
        // username and image_source are set in setUp() after creation
    ];
    protected $created_projectids = [];
    protected $TEST_TEXT = "This is a test file";
    protected $TEST_MODIFIED_TEXT = "This is a modified test file";

    protected function setUp(): void
    {
        create_test_user($this->TEST_USERNAME_PM);

        // make the user a PM
        $settings = new Settings($this->TEST_USERNAME_PM);
        $settings->set_boolean("manager", true);
        // and give them PP access
        $settings->set_boolean("PP.access", true);

        create_test_user($this->TEST_USERNAME);
        grant_user_access($this->TEST_USERNAME, "P1");

        // create an old user (new users $days_on_site_threshold = 21)
        create_test_user($this->TEST_OLDUSERNAME, 22);
        grant_user_access($this->TEST_OLDUSERNAME, "P1");

        create_test_image_source($this->TEST_IMAGESOURCE);

        $this->valid_project_data["username"] = $this->TEST_USERNAME_PM;
        $this->valid_project_data["image_source"] = $this->TEST_IMAGESOURCE;
    }

    protected function tearDown(): void
    {
        // clean up the PM value
        $settings = new Settings($this->TEST_USERNAME_PM);
        $settings->set_value("manager", null);

        delete_test_user($this->TEST_USERNAME_PM);
        delete_test_user($this->TEST_USERNAME);
        delete_test_user($this->TEST_OLDUSERNAME);
        delete_test_image_source($this->TEST_IMAGESOURCE);

        foreach ($this->created_projectids as $projectid) {
            $project = new Project($projectid);
            delete_test_project_remains($project);
        }
    }

    // helper function to create a project
    protected function _create_project(): Project
    {
        global $pguser;

        // save existing pguser and set to the PM we know exists
        $old_pguser = $pguser;
        $pguser = $this->TEST_USERNAME_PM;

        $project = new Project();
        foreach ($this->valid_project_data as $key => $value) {
            $project->$key = $value;
        }
        $project->save();
        $this->created_projectids[] = $project->projectid;
        $project->add_charsuite("basic-latin");

        // restore pguser
        $pguser = $old_pguser;

        return $project;
    }

    protected function quiet_project_transition(string $projectid, string $new_state, string $who, array $extras = []): void
    {
        ob_start();
        project_transition($projectid, $new_state, $who, $extras);
        ob_end_clean();
    }

    protected function _create_available_project(int $npage = 1): Project
    {
        $project = $this->_create_project_with_pages($npage);
        $projectid = $project->projectid;
        $this->quiet_project_transition($projectid, PROJ_P1_UNAVAILABLE, $this->TEST_USERNAME_PM);
        $this->quiet_project_transition($projectid, PROJ_P1_WAITING_FOR_RELEASE, $this->TEST_USERNAME_PM);
        $this->quiet_project_transition($projectid, PROJ_P1_AVAILABLE, PT_AUTO);
        return new Project($projectid);
    }

    protected function advance_to_round2(string $projectid): void
    {
        $this->quiet_project_transition($projectid, PROJ_P1_COMPLETE, PT_AUTO);
        $this->quiet_project_transition($projectid, PROJ_P2_WAITING_FOR_RELEASE, PT_AUTO);
        $this->quiet_project_transition($projectid, PROJ_P2_AVAILABLE, PT_AUTO);
    }

    protected function advance_to_round3(string $projectid): void
    {
        $this->quiet_project_transition($projectid, PROJ_P2_COMPLETE, PT_AUTO);
        $this->quiet_project_transition($projectid, PROJ_P3_WAITING_FOR_RELEASE, PT_AUTO);
        $this->quiet_project_transition($projectid, PROJ_P3_AVAILABLE, PT_AUTO);
    }

    protected function _create_project_with_pages(int $npage = 1): Project
    {
        $project = $this->_create_project();
        $project->add_charsuite("basic-latin");

        $page = 1;
        while ($page <= $npage) {
            $page_base = sprintf("%03d", $page);
            $page += 1;
            $this->add_page($project, $page_base);
        }
        return $project;
    }

    protected function add_page(Project $project, string $base): void
    {
        $txt_file_name = "$base.txt";
        $txt_file_path = "$project->dir/$txt_file_name";
        file_put_contents($txt_file_path, $this->TEST_TEXT);

        $image_file_name = "$base.png";
        $img_file_path = "$project->dir/$image_file_name";
        // write some data so file is not too short
        file_put_contents($img_file_path, str_repeat("This is a test image file", 10));

        project_add_page($project->projectid, $base, $image_file_name, $txt_file_path, $this->TEST_USERNAME_PM, time());
    }
}
