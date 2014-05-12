<?php

require_once("../../global/library.php");
ft_init_module_page();
$request = array_merge($_POST, $_GET);

$all_change_types = array("login", "logout", "account_created", "account_deleted", "admin_update",
  "client_update", "account_disabled_from_failed_logins", "permissions");


if (isset($_GET["reset"]))
{
  $_POST["client_id"] = "";
  $_POST["page"]      = 1;
  $_POST["change_types"] = $all_change_types;
  $_POST["date_range"] = "all";
  $_POST["date_from"] = "";
  $_POST["date_to"] = "";
}

$client_id    = ft_load_module_field("client_audit", "client_id", "client_id");
$page         = ft_load_module_field("client_audit", "page", "page", 1);
$change_types = ft_load_module_field("client_audit", "change_types", "change_types", $all_change_types);
$date_range   = ft_load_module_field("client_audit", "date_range", "date_range", "all");
$date_from    = ft_load_module_field("client_audit", "date_from", "date_from");
$date_to      = ft_load_module_field("client_audit", "date_to", "date_to");

$search_criteria = array(
  "per_page"     => 20,
  "page"         => $page,
  "client_id"    => $client_id,
  "change_types" => $change_types,
  "date_range"   => $date_range,
  "date_from"    => $date_from,
  "date_to"      => $date_to
);

if (isset($request["delete_all"]))
	list($g_success, $g_message) = ca_delete_all_in_current_search($search_criteria);
else if (isset($request["change_ids"]))
	list($g_success, $g_message) = ca_delete_changes($request["change_ids"]);

$search_query = ca_search_history($search_criteria);

// a bit sucky. Since this module tracks accounts that may have been deleted, we can't rely on pulling the
// name from the database. So - for each user in this result set, check to see if they exist or not and
// add an appropriate key for use by the template. This should be cached, but for v1 I'll leave it be
$search_results = array();
foreach ($search_query["results"] as $row)
{
  $row["account_exists"] = ft_account_exists($row["account_id"]);
  $search_results[] = $row;
}


// ------------------------------------------------------------------------------------------------

$page_vars = array();
$page_vars["clients"] = ca_get_logged_client_accounts();
$page_vars["deleted_clients"] = ca_get_deleted_logged_client_accounts();
$page_vars["search"] = $search;
$page_vars["pagination"] = ft_get_page_nav($search_query["num_search_results"], 20, $page, "");
$page_vars["search_results"] = $search_results;
$page_vars["total_count"] = $search_query["total_count"];
$page_vars["num_search_results"] = $search_query["num_search_results"];
$page_vars["search_criteria"] = $search_criteria;
$page_vars["head_js"] =<<< EOF
page_ns = {
  selectDateType: function(choice)
  {
    if (choice == "range")
    {
      $("date_from").disabled = false;
      $("date_to").disabled = false;
      $("from_calendar").style.display = "block";
      $("to_calendar").style.display = "block";
    }
    else
    {
      $("date_from").disabled = true;
      $("date_to").disabled = true;
      $("from_calendar").style.display = "none";
      $("to_calendar").style.display = "none";
    }
  }
};

Event.observe(window, "dom:loaded", function() {
  if ($("dr2").checked)
    page_ns.selectDateType("range");

  $("toggle").observe("click", function(e) {
    var is_checked = this.checked;
    $$("input.change_row").each(function(e) { e.checked = is_checked; });
  });

  $("client_audit_form").observe("submit", function(e) {
    if (!confirm("{$L["confirm_delete_rows"]}"))
      Event.stop(e);
  });
});
EOF;

$page_vars["head_string"] =<<< EOF
  <link type="text/css" rel="stylesheet" href="$g_root_url/modules/client_audit/global/styles.css">
  <script src="{$g_root_url}/global/scripts/manage_views.js"></script>
  <link rel="stylesheet" type="text/css" media="all" href="{$g_root_url}/global/jscalendar/skins/aqua/theme.css" title="Aqua" />
  <script src="{$g_root_url}/global/jscalendar/calendar.js"></script>
  <script src="{$g_root_url}/global/jscalendar/calendar-setup.js"></script>
  <script src="{$g_root_url}/global/jscalendar/lang/calendar-en.js"></script>
EOF;

ft_display_module_page("templates/index.tpl", $page_vars);