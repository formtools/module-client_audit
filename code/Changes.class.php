<?php

namespace FormTools\Modules\ClientAudit;

use FormTools\Accounts;
use FormTools\Clients;
use FormTools\Core;
use FormTools\General;
use PDO, Exception;


class Changes
{
    /**
     * This logs the change in the ft_module_client_audit_changes table and returns the change_id. Depending on
     * the change type, that value can then be used to populate the account change tables.
     *
     * @param string $change_type "account_created, "admin_update", "client_update", "account_disabled_from_failed_logins",
     *                            "account_deleted", "login", "logout"
     * @param int $account_id
     */
    public static function insertChangeRow($change_type, $account_id, $hidden = false)
    {
        $db = Core::$db;

        try {
            $db->query("
                INSERT INTO {PREFIX}module_client_audit_changes (change_date, change_type, status, account_id)
                VALUES (:change_date, :change_type, :status, :account_id)
            ");
            $db->bindAll(array(
                "change_date" => General::getCurrentDatetime(),
                "change_type" => $change_type,
                "status" => $hidden ? "hidden" : "visible",
                "account_id" => $account_id
            ));
            $db->execute();
        } catch (Exception $e) {
            return false;
        }

        return $db->getInsertId();
    }

    /**
     * Called after an account is updated. Technically there could be some timing issues where this
     * function reads the DB values after they've already been re-written, but it's extremely unlikely
     * due to the low load on client account changes. So for this initial version, I'm going to regard
     * that as acceptable.
     *
     * @param integer $account_id
     * @param integer $change_id
     */
    public static function updateAccountChangelog($account_id, $change_id, $last_account_info = false)
    {
        $db = Core::$db;

        // get the client account
        $account_info = Accounts::getAccountInfo($account_id);

        // figure out which fields have changed. Note: this checks both the contents of the accounts table AND
        // the account_settings table
        $changed_fields = array();
        if ($last_account_info !== false) {

            // compare the current content of the user's account with the last state change and log the results
            $fields = array(
                "account_status", "ui_language", "timezone_offset", "sessions_timeout", "date_format",
                "login_page", "logout_url", "theme", "swatch", "menu_id", "first_name", "last_name", "email", "username",
                "password"
            );

            foreach ($fields as $field) {
                if ($last_account_info[$field] != $account_info[$field]) {
                    $changed_fields[] = $field;
                }
            }

            // now compare the settings
            $diff1 = array_diff($account_info["settings"], $last_account_info["account_settings"]);
            $diff2 = array_diff($last_account_info["account_settings"], $account_info["settings"]);
            $diff = array_merge($diff1, $diff2);
            $changed_settings = array_keys($diff);
            $changed_fields = array_merge($changed_fields, $changed_settings);
        }

        try {

            // insert the new row, including the all-important changed_fields_str which logs what changed since
            // the last update
            $db->query("
                INSERT INTO {PREFIX}module_client_audit_accounts (change_id, changed_fields,
                    account_status, ui_language, timezone_offset, sessions_timeout, date_format, login_page,
                    logout_url, theme, swatch, menu_id, first_name, last_name, email, username, password)
                VALUES (:change_id, :changed_fields, :account_status, :ui_language, :timezone_offset,
                    :sessions_timeout, :date_format, :login_page, :logout_url, :theme, :swatch, :menu_id,
                    :first_name, :last_name, :email, :username, :password)
            ");
            $db->bindAll(array(
                "change_id" => $change_id,
                "changed_fields" => implode(",", $changed_fields),
                "account_status" => $account_info["account_status"],
                "ui_language" => $account_info["ui_language"],
                "timezone_offset" => $account_info["timezone_offset"],
                "sessions_timeout" => $account_info["sessions_timeout"],
                "date_format" => $account_info["date_format"],
                "login_page" => $account_info["login_page"],
                "logout_url" => $account_info["logout_url"],
                "theme" => $account_info["theme"],
                "swatch" => $account_info["swatch"],
                "menu_id" => $account_info["menu_id"],
                "first_name" => $account_info["first_name"],
                "last_name" => $account_info["last_name"],
                "email" => $account_info["email"],
                "username" => $account_info["username"],
                "password" => $account_info["password"]
            ));
            $db->execute();

            // if the main query was successful (it always SHOULD be) log the account settings. Note that we log ALL of them:
            // this is bad for space, but convenient. It lets us always be able to compare the last contents of the
            // settings table to figure out what's changed. Otherwise it would need to parse the entire history to determine
            // if and when a change has been made
            while (list($setting_name, $setting_value) = each($account_info["settings"])) {
                $db->query("
                    INSERT INTO {PREFIX}module_client_audit_account_settings (change_id, setting_name, setting_value)
                    VALUES (:change_id, :setting_name, :setting_value)
                ");
                $db->bindAll(array(
                    "change_id" => $change_id,
                    "setting_name" => $setting_name,
                    "setting_value" => $setting_value
                ));
                $db->execute();
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }


    /**
     * Returns the last contents of the accounts and account_settings tables for a particular account. The
     * account_settings values are stored as a hash in the account_settings key.
     *
     * If there is no record, it just returns false.
     *
     * @param integer $account_id
     * @returm mixed false or a hash
     */
    public static function getLastAccountState($account_id)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}module_client_audit_accounts a, {PREFIX}module_client_audit_changes c
            WHERE  a.change_id = c.change_id AND
                   c.account_id = :account_id
            ORDER BY c.change_id DESC
            LIMIT 1
        ");
        $db->bind("account_id", $account_id);
        $db->execute();

        $account_info = $db->fetch();

        // if there's no account info, that's fine too. The client simply hasn't done anything that would cause their
        // account info changes to get logged (i.e. they've only logged in, for instance)
        if (empty($account_info)) {
            return false;
        }
        $change_id = $account_info["change_id"];

        // now add the account settings
        $db->query("
            SELECT setting_name, setting_value
            FROM   {PREFIX}module_client_audit_account_settings
            WHERE  change_id = :change_id
        ");
        $db->bind("change_id", $change_id);
        $db->execute();

        $account_info["account_settings"] = $db->fetchAll(PDO::FETCH_KEY_PAIR);;

        return $account_info;
    }

    /**
     * Returns a list of all client accounts for which we have logs. Note: this doesn't return
     * any accounts that have been deleted.
     */
    public static function getLoggedClientAccounts()
    {
        $db = Core::$db;

        $db->query("
            SELECT account_id, a.first_name, a.last_name
            FROM   {PREFIX}accounts a
            WHERE  account_id IN (
            SELECT account_id
                FROM   {PREFIX}module_client_audit_changes mcac
                GROUP BY account_id
            )
            ORDER BY a.last_name
        ");
        $db->execute();

        return $db->fetchAll();
    }


    public static function getLoggedClientAccountsWithPermissions()
    {
        $db = Core::$db;

        $db->query("
            SELECT account_id, a.first_name, a.last_name
            FROM   {PREFIX}accounts a
            WHERE  account_id IN (
            SELECT account_id
                FROM   {PREFIX}module_client_audit_changes mcac
                WHERE  change_type = 'permissions'
                GROUP BY account_id
            )
            ORDER BY a.last_name
        ");
        $db->execute();

        return $db->fetchAll();
    }

    /**
     * Returns all deleted client accounts that have been logged in the system.
     */
    public static function getDeletedLoggedClientAccounts()
    {
        $db = Core::$db;

        $db->query("
            SELECT account_id
            FROM   {PREFIX}module_client_audit_changes
            WHERE  change_type = 'account_deleted'
        ");
        $db->execute();
        $account_ids = $db->fetchAll(PDO::FETCH_COLUMN);

        $accounts = array();
        foreach ($account_ids as $account_id) {

            // now grab the LAST logged record for this deleted account
            $db->query("
                SELECT *
                FROM   {PREFIX}module_client_audit_changes mcac, {PREFIX}module_client_audit_accounts mcaa
                WHERE  mcac.change_id = mcaa.change_id AND
                       mcac.account_id = :account_id
                ORDER BY mcac.change_id DESC
            ");
            $db->bind("account_id", $account_id);
            $db->execute();

            $accounts[] = $db->fetch();
        }

        return $accounts;
    }


    public static function searchHistory($search_criteria)
    {
        $db = Core::$db;

        $bindings = array();
        $where_clauses = array("status = 'visible'");
        if (!empty($search_criteria["client_id"])) {
            $where_clauses[] = "account_id = :account_id";
            $bindings["account_id"] = $search_criteria["client_id"];
        }

        // if the user didn't select any change types, they won't get any results
        $info = self::getChangeTypeClause($search_criteria["change_types"]);
        $where_clauses[] = $info["where_clause"];
        $bindings = array_merge($info["bindings"], $bindings);

        if (!empty($search_criteria["date_from"]) && !empty($search_criteria["date_to"])) {
            $where_clauses[] = "change_date >= :date_from AND change_date <= :date_to";
            $bindings["date_from"] = $search_criteria["date_from"] . "00:00:00";
            $bindings["date_to"] = $search_criteria["date_to"] . "23:59:59";
        }

        $page_num = (empty($search_criteria["page"])) ? 1 : $search_criteria["page"];

        $first_item = ($page_num - 1) * $search_criteria["per_page"];
        $limit_clause = "LIMIT $first_item, {$search_criteria["per_page"]}";

        $where_clause_str = (!empty($where_clauses)) ? "WHERE " . join(" AND ", $where_clauses) : "";

        $db->query("
            SELECT *
            FROM   {PREFIX}module_client_audit_changes
            $where_clause_str
            ORDER BY change_date DESC
            $limit_clause
        ");
        $db->bindAll($bindings);
        $db->execute();

        $results = $db->fetchAll();

        $db->query("
            SELECT count(*)
            FROM   {PREFIX}module_client_audit_changes
            $where_clause_str
        ");
        $db->bindAll($bindings);
        $db->execute();
        $num_search_results = $db->fetch(PDO::FETCH_COLUMN);

        $db->query("
            SELECT count(*) as c
            FROM   {PREFIX}module_client_audit_changes
            WHERE  status = 'visible'
        ");
        $db->bindAll($bindings);
        $db->execute();
        $total_num_results = $db->fetch(PDO::FETCH_COLUMN);

        return array(
            "results"            => $results,
            "num_search_results" => $num_search_results,
            "total_count"        => $total_num_results
        );
    }


    /**
     * This examines the current search and figures out what are the change IDs for the previous and
     * next links. This function is very similar to the ca_search_history function, except it only
     * looks at change types that have details - i.e. NOT logout or logins.
     *
     * @param integer $change_id
     * @param array $search_criteria
     * @return array
     */
    public static function getDetailsPageNavLinks($change_id, $search_criteria)
    {
        $db = Core::$db;

        $where_clauses = array(
            "status = 'visible'",
            "(change_type != 'login' AND change_type != 'logout')
        ");
        $bindings = array();

        if (!empty($search_criteria["client_id"])) {
            $where_clauses[] = "account_id = :account_id";
            $bindings["account_id"] = $search_criteria["client_id"];
        }

        $info = self::getChangeTypeClause($search_criteria["change_types"]);
        $where_clauses[] = $info["where_clause"];
        $bindings = array_merge($info["bindings"], $bindings);

        if (!empty($search_criteria["date_from"]) && !empty($search_criteria["date_to"])) {
            $where_clauses[] = "change_date >= :date_from AND change_date <= :date_to";
            $bindings["date_from"] = $search_criteria["date_from"] . "00:00:00";
            $bindings["date_to"] = $search_criteria["date_to"] . "23:59:59";
        }

        $where_clause_str = (!empty($where_clauses)) ? "WHERE " . join(" AND ", $where_clauses) : "";

        $db->query("
            SELECT change_id
            FROM   {PREFIX}module_client_audit_changes
                   $where_clause_str
            ORDER BY change_date DESC
        ");
        $db->bindAll($bindings);
        $db->execute();

        $change_ids = $db->fetchAll(PDO::FETCH_COLUMN);
        $previous_change_id = "";
        $next_change_id     = "";

        $index = array_search($change_id, $change_ids);

        if ($index > 0) {
            $previous_change_id = $change_ids[$index - 1];
        }
        if ($index < count($change_ids) - 1) {
            $next_change_id = $change_ids[$index + 1];
        }

        return array(
            "previous_change_id" => $previous_change_id,
            "next_change_id"     => $next_change_id
        );
    }


    public static function getLastAccountPermissions($account_id)
    {
        $db = Core::$db;

        // get the latest permissions for this account
        $db->query("
            SELECT permissions
            FROM   {PREFIX}module_client_audit_client_permissions p, {PREFIX}module_client_audit_changes ch
            WHERE  p.change_id = ch.change_id AND
                   ch.change_type = 'permissions' AND 
                   ch.account_id = :account_id
            ORDER BY ch.change_id DESC
        ");
        $db->bind("account_id", $account_id);
        $db->execute();

        $permissions = $db->fetch(PDO::FETCH_COLUMN);

        return isset($permissions) ? $permissions : "";
    }


    public static function updateAccountPermissions($account_id, $change_id)
    {
        $db = Core::$db;

        $old_permissions_serialized = self::getLastAccountPermissions($account_id);
        $old_permissions            = self::deserializePermissionString($old_permissions_serialized);
        $new_permissions_serialized = self::getSerializedPermissionString($account_id);
        $new_permissions            = self::deserializePermissionString($new_permissions_serialized);

        $new_forms = array_keys($new_permissions);
        $old_forms = array_keys($old_permissions);

        $added_forms   = array_diff($new_forms, $old_forms);
        $removed_forms = array_diff($old_forms, $new_forms);

        $same_forms    = array_intersect($new_forms, $old_forms);

        // loop through the
        $added_views   = array();
        $removed_views = array();
        while (list($form_id, $old_view_ids) = each($old_permissions)) {
            if (!in_array($form_id, $same_forms)) {
                continue;
            }
            $new_view_ids = $new_permissions[$form_id];
            $added_views   = array_merge($added_views, array_diff($new_view_ids, $old_view_ids));
            $removed_views = array_merge($removed_views, array_diff($old_view_ids, $new_view_ids));
        }

        $added_forms_str = implode(",", $added_forms);
        $removed_forms_str = implode(",", $removed_forms);
        $added_views_str = implode(",", $added_views);
        $removed_views_str = implode(",", $removed_views);

        $db->query("
            INSERT INTO {PREFIX}module_client_audit_client_permissions (change_id, added_views,
                removed_views, added_forms, removed_forms, permissions)
            VALUES (:change_id, :added_views, :removed_views, :added_forms, :removed_forms, :permissions)
        ");
        $db->bindAll(array(
            "change_id" => $change_id,
            "added_views" => $added_views_str,
            "removed_views" => $removed_views_str,
            "added_forms" => $added_forms_str,
            "removed_forms" => $removed_forms_str,
            "permissions" => $new_permissions_serialized
        ));
        $db->execute();
    }

    /**
     * This parses an account ID and serializes their permissions - form IDs and view IDs
     *
     * @param integer $account_id
     * @return string
     */
    public static function getSerializedPermissionString($account_id)
    {
        $permissions = Clients::getClientFormViews($account_id);
        asort($permissions, SORT_NUMERIC);

        $str = "";
        while (list($form_id, $view_ids) = each($permissions)) {
            $str .= "$form_id:";
            sort($view_ids, SORT_NUMERIC);
            $str .= implode(",", $view_ids) . "|";
        }

        return $str;
    }


    /**
     * This examines a permission string and returns a hash of form IDs => (array) view IDs.
     *
     * @param string $permission_str
     */
    public static function deserializePermissionString($permission_str)
    {
        $forms_and_views = explode("|", $permission_str);

        $data = array();
        foreach ($forms_and_views as $form_info) {
            if (empty($form_info)) {
                continue;
            }
            list($form_id, $view_str) = explode(":", $form_info);
            $view_ids = explode(",", $view_str);
            $data[$form_id] = $view_ids;
        }

        return $data;
    }

    public static function deleteChanges($change_ids = array(), $L)
    {
        $db = Core::$db;

        foreach ($change_ids as $change_id) {
            $db->query("DELETE FROM {PREFIX}module_client_audit_accounts WHERE change_id = :change_id");
            $db->bind("change_id", $change_id);
            $db->execute();

            $db->query("DELETE FROM {PREFIX}module_client_audit_account_settings WHERE change_id = :change_id");
            $db->bind("change_id", $change_id);
            $db->execute();

            $db->query("DELETE FROM {PREFIX}module_client_audit_changes WHERE change_id = :change_id");
            $db->bind("change_id", $change_id);
            $db->execute();

            $db->query("DELETE FROM {PREFIX}module_client_audit_client_permissions WHERE change_id = :change_id");
            $db->bind("change_id", $change_id);
            $db->execute();
        }

        return array(true, $L["notify_changes_deleted"]);
    }

    /**
     * Deletes everything in the current search.
     *
     * @param array $search_criteria
     */
    public static function deleteAllInCurrentSearch($search_criteria, $L)
    {
        $db = Core::$db;

        $bindings = array();
        $where_clauses = array("status = 'visible'");

        if (!empty($search_criteria["client_id"])) {
            $where_clauses[] = "account_id = :account_id";
            $bindings["account_id"] = $search_criteria["client_id"];
        }

        $info = self::getChangeTypeClause($search_criteria["change_types"]);
        $where_clauses[] = $info["where_clause"];
        $bindings = array_merge($info["bindings"], $bindings);

        if (!empty($search_criteria["date_from"]) && !empty($search_criteria["date_to"])) {
            $where_clauses[] = "change_date >= '{$search_criteria["date_from"]} 00:00:00' AND change_date <= '{$search_criteria["date_to"]} 23:59:59'";
        }

        $where_clause_str = (!empty($where_clauses)) ? "WHERE " . join(" AND ", $where_clauses) : "";

        $db->query("
            SELECT change_id
            FROM   {PREFIX}module_client_audit_changes
            $where_clause_str
            ORDER BY change_date DESC
        ");
        $db->bindAll($bindings);
        $db->execute();
        $change_ids = $db->fetchAll(PDO::FETCH_COLUMN);

        return self::deleteChanges($change_ids, $L);
    }


    /**
     * Old comment:
     *
     * "this is a bit weird, but the original search query COULD have included login's and logouts. But the former
     * $where_clause states NOT to return those values. This is correct, albeit a bit weird if you happen to be
     * looking at the actual query being used"
     *
     * @param $change_types
     * @return array
     */
    public static function getChangeTypeClause($change_types)
    {
        $bindings = array();
        $change_types_clause = "change_type = ''";
        if (isset($change_types) && !empty($change_types)) {
            $change_types_clauses = array();
            for ($i=0; $i<count($change_types); $i++) {
                $change_types_clauses[] = "change_type = :change_type_{$i}";
                $bindings["change_type_{$i}"] = $change_types[$i];
            }
            $change_types_clause = "(" . implode(" OR ", $change_types_clauses) . ")";
        }

        return array(
            "where_clause" => $change_types_clause,
            "bindings" => $bindings
        );
    }

    /**
     * This returns the details of a particular change. It's only ever called on permissions and
     * details changes - i.e. changes that have other entries in the module_client_audit_accounts,
     * module_client_audit_account_settings and module_client_audit_permissions tables.
     *
     * @param integer $change_id
     * @return array
     */
    public static function getChange($change_id)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}module_client_audit_changes
            WHERE  change_id = :change_id
        ");
        $db->bind("change_id", $change_id);
        $db->execute();

        $change_info = $db->fetch();

        $change_type = $change_info["change_type"];
        if ($change_type == "permissions") {
            $db->query("
                SELECT *
                FROM   {PREFIX}module_client_audit_client_permissions
                WHERE  change_id = :change_id
            ");
            $db->bind("change_id", $change_id);
            $db->execute();
            $change_info["permissions"] = $db->fetch();
        } else {
            $db->query("
                SELECT *
                FROM   {PREFIX}module_client_audit_accounts
                WHERE  change_id = :change_id
            ");
            $db->bind("change_id", $change_id);
            $db->execute();
            $change_info["account_info"] = $db->fetch();

            $db->query("
                SELECT setting_name, setting_value
                FROM   {PREFIX}module_client_audit_account_settings
                WHERE  change_id = :change_id
            ");
            $db->bind("change_id", $change_id);
            $db->execute();
            $change_info["account_settings"] = $db->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        return $change_info;
    }

    public static function accountHasChanged($account_id, $old_account_info)
    {
        $account_info = Accounts::getAccountInfo($account_id);

        // figure out which fields have changed. Note: this checks both the contents of the accounts table AND
        // the account_settings table
        $changed_fields = array();
        $fields = array(
            "account_status", "ui_language", "timezone_offset", "sessions_timeout", "date_format", "login_page",
            "logout_url", "theme", "swatch", "menu_id", "first_name", "last_name", "email", "username", "password"
        );

        foreach ($fields as $field) {
            if ($old_account_info[$field] != $account_info[$field]) {
                $changed_fields[] = $field;
            }
        }

        // now compare the settings
        $diff1 = array_diff($account_info["settings"], $old_account_info["account_settings"]);
        $diff2 = array_diff($old_account_info["account_settings"], $account_info["settings"]);
        $diff = array_merge($diff1, $diff2);
        $changed_settings = array_keys($diff);
        $changed_fields = array_merge($changed_fields, $changed_settings);

        return !empty($changed_fields);
    }

}

