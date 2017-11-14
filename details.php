<?php

require_once("../../global/library.php");

use FormTools\Accounts;
use FormTools\Core;
use FormTools\Modules;
use FormTools\Modules\ClientAudit\Changes;
use FormTools\Views;

$module = Modules::initModulePage("admin");
$LANG = Core::$L;

$change_id = $request["change_id"];
$change_info = Changes::getChange($change_id);

$changes = array();
if (isset($change_info["account_info"]["changed_fields"]) && !empty($change_info["account_info"]["changed_fields"])) {
    $changes = explode(",", $change_info["account_info"]["changed_fields"]);
}

$permissions = "";
$added_forms   = array();
$removed_forms = array();
$added_views   = array();
$removed_views = array();
$all_form_views = array();
if ($change_info["change_type"] == "permissions") {
    $permissions = Changes::deserializePermissionString($change_info["permissions"]["permissions"]);

    $form_ids = array_keys($permissions);
    foreach ($form_ids as $form_id) {
        $all_form_views[$form_id] = Views::getFormViews($form_id);
    }
    if (!empty($change_info["permissions"]["added_forms"])) {
        $added_forms = explode(",", $change_info["permissions"]["added_forms"]);
    }
    if (!empty($change_info["permissions"]["removed_forms"])) {
        $removed_forms = explode(",", $change_info["permissions"]["removed_forms"]);
    }
    if (!empty($change_info["permissions"]["added_views"])) {
        $added_views = explode(",", $change_info["permissions"]["added_views"]);
    }
    if (!empty($change_info["permissions"]["removed_views"])) {
        $removed_views = explode(",", $change_info["permissions"]["removed_views"]);
    }
}

// now figure out which settings have actually changed since the last update
$changed_settings = array();
if (isset($change_info["account_settings"])) {
    while (list($setting_name, $setting_value) = each($change_info["account_settings"])) {
        if (in_array($setting_name, $changes)) {
            $changed_settings[$setting_name] = $setting_value;
        }
    }
}

$all_change_types = array(
    "login", "logout", "account_created", "account_deleted", "admin_update",
    "client_update", "account_disabled_from_failed_logins", "permissions"
);

$client_id    = Modules::loadModuleField("client_audit", "client_id", "client_id");
$page         = Modules::loadModuleField("client_audit", "page", "page", 1);
$change_types = Modules::loadModuleField("client_audit", "change_types", "change_types", $all_change_types);
$date_range   = Modules::loadModuleField("client_audit", "date_range", "date_range", "all");
$date_from    = Modules::loadModuleField("client_audit", "date_from", "date_from");
$date_to      = Modules::loadModuleField("client_audit", "date_to", "date_to");

$search_criteria = array(
    "per_page"     => 20,
    "page"         => $page,
    "client_id"    => $client_id,
    "change_types" => $change_types,
    "date_range"   => $date_range,
    "date_from"    => $date_from,
    "date_to"      => $date_to
);

$nav_info = Changes::getDetailsPageNavLinks($change_id, $search_criteria);

$settings_labels = array(
    "company_name" => $LANG["phrase_company_name"],
    "footer_text"  => $LANG["phrase_footer_text"],
    "max_failed_login_attempts" => $LANG["phrase_auto_disable_account"],
    "min_password_length" => $LANG["phrase_min_password_length"],
    "num_password_history" => $LANG["phrase_prevent_password_reuse"],
    "required_password_chars" => $LANG["phrase_required_password_chars"],
    "page_titles" => $LANG["phrase_page_titles"]
);

$page_vars = array(
    "search_criteria"  => $search_criteria,
    "change_info"      => $change_info,
    "changes"          => $changes,
    "changed_settings" => $changed_settings,
    "nav_info"         => $nav_info,
    "settings_labels"  => $settings_labels,
    "permissions"      => $permissions,
    "added_forms"      => $added_forms,
    "removed_forms"    => $removed_forms,
    "added_views"      => $added_views,
    "removed_views"    => $removed_views,
    "all_form_views"   => $all_form_views,
    "account_exists"   => Accounts::accountExists($change_info["account_id"])
);

$module->displayPage("templates/details.tpl", $page_vars);
