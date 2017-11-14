<?php

use FormTools\Core;

/*
 * Displays the name of a deleted account. Since it's deleted it doesn't exist in the accounts table anymore, we pull
 * it from the database logs instead.
 */
function smarty_function_display_deleted_account_name($params, &$smarty)
{
    $db = Core::$db;

    $account_id = (isset($params["account_id"])) ? $params["account_id"] : "";
    $format     = (isset($params["format"])) ? $params["format"] : "first_last";

    if (empty($account_id)) {
        return "";
    }

    $db->query("
        SELECT *
        FROM   {PREFIX}module_client_audit_accounts mcaa, {PREFIX}module_client_audit_changes mcac
        WHERE  mcac.change_id = mcaa.change_id AND
               mcac.change_type != 'account_deleted' AND
               mcac.change_type != 'login' AND
               mcac.change_type != 'logout' AND
               mcac.account_id = :account_id
        ORDER BY mcaa.change_id DESC
        LIMIT 1
    ");
    $db->bind("account_id", $account_id);
    $db->execute();

    $account_info = $db->fetch();

    // neat fringe case. If the user just installed the module then deleted an account, there won't be the
    // name of the account in the logs, so it won't return anything.
    if (empty($account_info)) {
        $html = "Unknown";
    } else {
        if ($format == "first_last") {
            $html = "{$account_info["first_name"]} {$account_info["last_name"]}";
        } else {
            $html = "{$account_info["last_name"]}, {$account_info["first_name"]}";
        }
    }

    return $html;
}
