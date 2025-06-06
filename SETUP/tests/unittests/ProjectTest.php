<?php

class ProjectTest extends ProjectUtils
{
    protected function tearDown(): void
    {
        global $pguser;

        parent::tearDown();

        // reset $pguser after every test
        $pguser = null;
    }

    //------------------------------------------------------------------------
    // Project object save and delete

    public function test_save_create(): void
    {
        $project = $this->_create_project();

        // test that the project event was created
        $events = load_project_events($project);
        $last_event = array_pop($events);
        $this->assertEquals("creation", $last_event["event_type"]);

        // test that the project was updated in the DB
        $check_project = new Project($project->projectid);
        $this->assertEquals($project->nameofwork, $check_project->nameofwork);

        // confirm the project directory was created
        $this->assertTrue(is_dir($project->dir), "Project dir created: $project->dir");
    }

    public function test_save_update(): void
    {
        global $pguser;

        $project = $this->_create_project();

        $pguser = $this->TEST_USERNAME_PM;
        // update title and save
        $project->nameofwork = "New Name";
        $project->save();

        // test that the project event was created
        $events = load_project_events($project);
        $last_event = array_pop($events);
        $this->assertEquals("edit", $last_event["event_type"]);
        $this->assertEquals("nameofwork", $last_event["details1"]);

        // test that the project was updated in the DB
        $check_project = new Project($project->projectid);
        $this->assertEquals("New Name", $check_project->nameofwork);
    }

    public function test_save_init_from_array(): void
    {
        $this->expectException(ProjectException::class);
        $project = new Project($this->valid_project_data);
        $project->save();
    }

    public function test_delete(): void
    {
        $project = $this->_create_project();

        $project->delete();

        // clear PHP's stat cache
        clearstatcache();

        // confirm the project directory was deleted
        $this->assertTrue(!is_dir($project->dir), "Project dir deleted: $project->dir");

        // confirm the project's pages table does not exist
        $this->assertTrue(!does_project_page_table_exist($project->projectid), "Pages table deleted");
    }

    //------------------------------------------------------------------------
    // Language handling functions

    public function test_encode_languages_single(): void
    {
        $language = Project::encode_languages(["First"]);
        $this->assertEquals("First", $language);
    }

    public function test_encode_languages_single_with_blank(): void
    {
        $language = Project::encode_languages(["First", ""]);
        $this->assertEquals("First", $language);
    }

    public function test_encode_languages_double(): void
    {
        $language = Project::encode_languages(["First", "Second"]);
        $this->assertEquals("First with Second", $language);
    }

    public function test_decode_languages_single(): void
    {
        $languages = Project::decode_language("First");
        $this->assertEquals(["First"], $languages);
    }

    public function test_decode_languages_double(): void
    {
        $languages = Project::decode_language("First with Second");
        $this->assertEquals(["First", "Second"], $languages);
    }

    public function test_project_languages_setter_single(): void
    {
        $project = new Project($this->valid_project_data);
        $project->languages = ["First"];
        $this->assertEquals("First", $project->language);
    }

    public function test_project_languages_setter_double(): void
    {
        $project = new Project($this->valid_project_data);
        $project->languages = ["First", "Second"];
        $this->assertEquals("First with Second", $project->language);
    }

    //------------------------------------------------------------------------
    // Project object validation

    public function test_validate_required_fields_positive_path(): void
    {
        $project = new Project($this->valid_project_data);
        $errors = $project->validate();
        $this->assertEquals([], $errors);
    }

    public function test_validate_required_fields_negative_path(): void
    {
        // defaults are not sufficient for validation; test exception raised
        $project = new Project();
        $this->expectException(ProjectException::class);
        $project->validate(true);
    }

    public function test_validate_nameofwork_missing(): void
    {
        $project = new Project($this->valid_project_data);
        $project->nameofwork = '';
        $errors = $project->validate();
        $this->assertStringContainsString("required", $errors[0]);
    }

    public function test_validate_authorsname_missing(): void
    {
        $project = new Project($this->valid_project_data);
        $project->authorsname = '';
        $errors = $project->validate();
        $this->assertStringContainsString("required", $errors[0]);
    }

    public function test_validate_pm_missing(): void
    {
        $project = new Project($this->valid_project_data);
        $project->username = '';
        $errors = $project->validate();
        $this->assertStringContainsString("required", $errors[0]);
    }

    public function test_validate_pm_invalid(): void
    {
        $project = new Project($this->valid_project_data);
        $project->username = 'ProjectTest_FakeUser';
        $errors = $project->validate();
        $this->assertStringContainsString("must be an existing user", $errors[0]);
    }

    public function test_validate_pm_not_a_pm(): void
    {
        $project = new Project($this->valid_project_data);
        $project->username = $this->TEST_USERNAME;
        $errors = $project->validate();
        $this->assertStringContainsString("not a PM", $errors[0]);
    }

    public function test_validate_language_missing(): void
    {
        $project = new Project($this->valid_project_data);
        $project->language = '';
        $errors = $project->validate();
        $this->assertStringContainsString("required", $errors[0]);
    }

    public function test_validate_language_invalid(): void
    {
        $project = new Project($this->valid_project_data);
        $project->language = 'Fake Language';
        $errors = $project->validate();
        $this->assertStringContainsString("not a valid language", $errors[0]);
    }

    public function test_validate_language_duplicate(): void
    {
        $project = new Project($this->valid_project_data);
        $project->languages = ['English', 'English'];
        $errors = $project->validate();
        $this->assertStringContainsString("Languages must be unique.", $errors[0]);
    }

    public function test_validate_genre_missing(): void
    {
        $project = new Project($this->valid_project_data);
        $project->genre = '';
        $errors = $project->validate();
        $this->assertStringContainsString("required", $errors[0]);
    }

    public function test_validate_genre_invalid(): void
    {
        $project = new Project($this->valid_project_data);
        $project->genre = 'Fake Genre';
        $errors = $project->validate();
        $this->assertStringContainsString("not a valid genre", $errors[0]);
    }

    public function test_validate_difficulty_missing(): void
    {
        $project = new Project($this->valid_project_data);
        $project->difficulty = '';
        $errors = $project->validate();
        $this->assertStringContainsString("required", $errors[0]);
    }

    public function test_validate_difficulty_invalid(): void
    {
        $project = new Project($this->valid_project_data);
        $project->difficulty = 'insanely_hard';
        $errors = $project->validate();
        $this->assertStringContainsString("not a valid difficulty", $errors[0]);
    }

    public function test_validate_otherday_positive_path(): void
    {
        $project = new Project($this->valid_project_data);
        $project->special_code = "Otherday 0101";
        $errors = $project->validate();
        $this->assertEquals([], $errors);
    }

    public function test_validate_otherday_invalid(): void
    {
        $project = new Project($this->valid_project_data);
        $project->special_code = "Otherday 9901";
        $errors = $project->validate();
        $this->assertStringContainsString("Invalid date supplied", $errors[0]);
    }

    public function test_validate_postdnum_positive_path(): void
    {
        $project = new Project($this->valid_project_data);
        $project->postednum = 123;
        $errors = $project->validate();
        $this->assertEquals([], $errors);
    }

    public function test_validate_postedenum_invalid(): void
    {
        $this->expectException(TypeError::class);
        $project = new Project($this->valid_project_data);
        $project->postednum = "invalid";
    }

    public function test_validate_custom_chars_positive_path(): void
    {
        $project = new Project($this->valid_project_data);
        $project->custom_chars = '1';
        $errors = $project->validate();
        $this->assertEquals([], $errors);
    }

    public function test_validate_custom_chars_duplicates(): void
    {
        $project = new Project($this->valid_project_data);
        $project->custom_chars = '11';
        $errors = $project->validate();
        $this->assertStringContainsString("must be unique", $errors[0]);
    }

    public function test_validate_custom_chars_too_many(): void
    {
        $project = new Project($this->valid_project_data);
        $project->custom_chars = 'abcdefghijklmnopqrstuvwxyABCDEFGHJKLMNOPQRSTUVWXYZ';
        $errors = $project->validate();
        $this->assertStringContainsString("maximum of 32", $errors[0]);
    }

    public function test_validate_custom_chars_invalid(): void
    {
        $project = new Project($this->valid_project_data);
        $project->custom_chars = '…';
        $errors = $project->validate();
        $this->assertStringContainsString("are not allowed", $errors[0]);
    }

    //------------------------------------------------------------------------
    // projectID validation

    public function test_validate_projectID_positive_path(): void
    {
        validate_projectID($this->valid_projectID);

        // PHPUnit labels tests without at least one assert as risky
        $this->assertTrue(true);
    }

    public function test_validate_projectID_negative_path(): void
    {
        $this->expectException(InvalidProjectIDException::class);
        validate_projectID("1234");
    }

    public function test_get_projectID_param_positive_path(): void
    {
        $params = [
            "projectid" => $this->valid_projectID,
        ];
        $projectid = get_projectID_param($params, "projectid");
        $this->assertEquals($this->valid_projectID, $projectid);
    }

    public function test_get_projectID_param_null_positive_path(): void
    {
        $params = [];
        $projectid = get_projectID_param($params, "projectid", true);
        $this->assertEquals(null, $projectid);
    }

    public function test_get_projectID_param_null_negative_path(): void
    {
        $this->expectException(InvalidProjectIDException::class);
        $params = [];
        $projectid = get_projectID_param($params, "projectid");
    }

    //------------------------------------------------------------------------
    // page image validation

    public function test_validate_page_image_positive_path(): void
    {
        validate_page_image($this->valid_page_image);

        // PHPUnit labels tests without at least one assert as risky
        $this->assertTrue(true);
    }

    public function test_validate_page_image_negative_path(): void
    {
        $this->expectException(InvalidPageException::class);
        validate_page_image("1234");
    }

    public function test_get_page_image_param_positive_path(): void
    {
        $params = [
            "image" => $this->valid_page_image,
        ];
        $image = get_page_image_param($params, "image");
        $this->assertEquals($this->valid_page_image, $image);
    }

    public function test_get_page_image_param_null_positive_path(): void
    {
        $params = [];
        $image = get_page_image_param($params, "image", true);
        $this->assertEquals(null, $image);
    }

    public function test_get_page_image_param_null_negative_path(): void
    {
        $this->expectException(InvalidPageException::class);
        $params = [];
        $image = get_page_image_param($params, "image");
    }

    // tests for validate_can_be_proofed_by_current_user()

    public function test_can_be_proofed_not_in_round(): void
    {
        global $pguser;

        $this->expectExceptionCode(110);
        $pguser = $this->TEST_USERNAME;
        $project = $this->_create_project_with_pages();
        $project->validate_can_be_proofed_by_current_user();
    }

    public function test_can_be_proofed_not_available(): void
    {
        global $pguser;

        $this->expectExceptionCode(112);
        $pguser = $this->TEST_USERNAME;
        $project = $this->_create_project_with_pages();
        $this->quiet_project_transition($project->projectid, PROJ_P1_UNAVAILABLE, $this->TEST_USERNAME_PM);
        $project = new Project($project->projectid);
        $project->validate_can_be_proofed_by_current_user();
    }

    public function test_can_be_proofed_available(): void
    {
        global $pguser;

        $pguser = $this->TEST_USERNAME;
        $project = $this->_create_available_project();
        $project->validate_can_be_proofed_by_current_user();
        $this->assertTrue(true);
    }

    public function test_can_be_proofed_user_not_logged_in(): void
    {
        global $pguser;
        $pguser = null;

        $this->expectExceptionCode(301);
        $project = $this->_create_available_project();
        $project->validate_can_be_proofed_by_current_user();
    }

    public function test_can_be_proofed_user_not_qualified(): void
    {
        global $pguser;
        $pguser = $this->TEST_USERNAME;
        $user = new User($pguser);
        $user->deny_access("P2", $pguser);
        $this->expectExceptionCode(302);
        $project = $this->_create_available_project();
        $this->advance_to_round2($project->projectid);
        $project = new Project($project->projectid);
        $project->validate_can_be_proofed_by_current_user();
    }

    // tests for validate_user_can_get_pages_in_project()

    public function test_can_user_get_pages_reserved_for_new_proofreaders(): void
    {
        $user = new User($this->TEST_USERNAME);
        $project = $this->_create_available_project();
        $round = get_Round_for_round_id("P1");

        // user done no pages and few days on site
        validate_user_can_get_pages_in_project($user, $project, $round);

        // user done many pages
        // $page_tally_threshold 500 for new projects in reserve time
        page_tallies_add("P1", $user->username, 501);
        validate_user_can_get_pages_in_project($user, $project, $round);

        // few pages, many days on site
        $user = new User($this->TEST_OLDUSERNAME);
        validate_user_can_get_pages_in_project($user, $project, $round);

        // many pages, many days on site
        page_tallies_add("P1", $user->username, 501);
        $this->expectExceptionCode(306);
        validate_user_can_get_pages_in_project($user, $project, $round);
    }

    public function test_beginner_project_checkout(): void
    {
        $user = new User($this->TEST_USERNAME);
        $this->valid_project_data["difficulty"] = "beginner";
        $project = $this->_create_available_project();
        // beginners limit is 40 in user_is.inc
        page_tallies_add("P1", $user->username, 50);
        $round = get_Round_for_round_id("P1");
        $this->expectExceptionCode(303);
        validate_user_can_get_pages_in_project($user, $project, $round);
    }

    public function test_beginner_mentor_project_checkout(): void
    {
        $this->valid_project_data["difficulty"] = "beginner";
        $project = $this->_create_available_project();
        $this->advance_to_round2($project->projectid);
        $user = new User($this->TEST_USERNAME);
        $user->grant_access("P2", $this->TEST_USERNAME);
        $round = get_Round_for_round_id("P2");
        $this->expectExceptionCode(305);
        validate_user_can_get_pages_in_project($user, $project, $round);
    }

    public function test_project_checkout_no_more_pages(): void
    {
        global $pguser;
        $project = $this->_create_available_project();
        $pguser = $this->TEST_USERNAME;
        $round = get_Round_for_round_id("P1");
        [$imagefile, $state] = get_available_proof_page_array($project, $round, $pguser);
        $lpage = new LPage($project, $imagefile, $state, 0);
        $lpage->checkout($pguser);
        $this->assertEquals("P1.page_out", $lpage->page_state);

        // try to get another page
        $this->expectExceptionCode(113);
        get_available_proof_page_array($project, $round, $pguser);
    }

    public function test_project_has_entered_formatting_round(): void
    {
        $project = $this->_create_project();
        $project->state = "P1.proj_avail";
        $this->assertFalse($project->has_entered_formatting_round());
        $project->state = "F1.proj_avail";
        $this->assertTrue($project->has_entered_formatting_round());
    }
}
