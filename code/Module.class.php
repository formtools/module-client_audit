<?php

namespace FormTools\Modules\ClientAudit;

use FormTools\Clients;
use FormTools\Core;
use FormTools\General;
use FormTools\Hooks;
use FormTools\Module as FormToolsModule;
use FormTools\Sessions;
use Exception;


class Module extends FormToolsModule
{
    protected $moduleName = "Client Audit";
    protected $moduleDesc = "This module keeps a paper trail of changes to all client accounts, from the moment they were created until they are deleted. It tracks all logins, logout, permission changes and account updates, which can helpful for security auditing purposes.";
    protected $author = "Ben Keen";
    protected $authorEmail = "ben.keen@gmail.com";
    protected $authorLink = "https://formtools.org";
    protected $version = "2.0.2";
    protected $date = "2017-12-15";
    protected $originLanguage = "en_us";

    protected $jsFiles = array(
        "{FTROOT}/global/scripts/manage_views.js"
    );

    protected $cssFiles = array(
        "{MODULEROOT}/css/styles.css",
    );

    protected $nav = array(
        "module_name" => array("index.php", false),
        "word_help"   => array("help.php", true)
    );

    /**
     * The installation script for the module.
     */
    public function install($module_id)
    {
        $db = Core::$db;

        try {
            $db->beginTransaction();

            $db->query("
                CREATE TABLE {PREFIX}module_client_audit_accounts (
                    change_id mediumint(8) unsigned NOT NULL,
                    changed_fields mediumtext,
                    account_status enum('active','disabled','pending') NOT NULL default 'disabled',
                    ui_language varchar(50) NOT NULL default 'en_us',
                    timezone_offset varchar(4) default NULL,
                    sessions_timeout varchar(10) NOT NULL default '30',
                    date_format varchar(50) NOT NULL default 'M jS, g:i A',
                    login_page varchar(50) NOT NULL default 'client_forms',
                    logout_url varchar(255) default NULL,
                    theme varchar(50) NOT NULL default 'default',
                    swatch varchar(255) NOT NULL,
                    menu_id mediumint(8) unsigned NOT NULL,
                    first_name varchar(100) default NULL,
                    last_name varchar(100) default NULL,
                    email varchar(200) default NULL,
                    username varchar(50) default NULL,
                    password varchar(50) default NULL,
                    PRIMARY KEY  (change_id)
                ) DEFAULT CHARSET=utf8
            ");
            $db->execute();

            $db->query("
                CREATE TABLE {PREFIX}module_client_audit_account_settings (
                    change_id mediumint(9) NOT NULL,
                    setting_name varchar(255) NOT NULL,
                    setting_value mediumtext NOT NULL,
                    PRIMARY KEY  (change_id,setting_name)
                ) DEFAULT CHARSET=utf8
            ");
            $db->execute();

            $db->query("
                CREATE TABLE {PREFIX}module_client_audit_changes (
                    change_id mediumint(8) unsigned NOT NULL auto_increment,
                    change_date datetime NOT NULL,
                    change_type enum('account_created','account_deleted','admin_update','client_update','account_disabled_from_failed_logins','permissions','login','logout') character set latin1 NOT NULL,
                    status enum('hidden','visible') NOT NULL default 'visible',
                    account_id mediumint(9) NOT NULL,
                    PRIMARY KEY (change_id)
                ) DEFAULT CHARSET=utf8
            ");
            $db->execute();

            $db->query("
                CREATE TABLE {PREFIX}module_client_audit_client_permissions (
                    change_id mediumint(8) unsigned NOT NULL,
                    added_views mediumtext,
                    removed_views mediumtext,
                    added_forms mediumtext,
                    removed_forms mediumtext,
                    permissions mediumtext NOT NULL,
                    PRIMARY KEY (change_id)
                ) DEFAULT CHARSET=utf8
            ");
            $db->execute();
            $db->processTransaction();

        } catch (Exception $e) {
            $db->rollbackTransaction();

            $L = $this->getLangStrings();
            $message = General::evalSmartyString($L["notify_problem_installing"], array("error" => $e->getMessage()));
            return array(false, $message);
        }

        Hooks::registerHook("code", "client_audit", "main", "FormTools\\User->login", "logChange");
        Hooks::registerHook("code", "client_audit", "main", "FormTools\\User->logout", "logChange");
        Hooks::registerHook("code", "client_audit", "end", "FormTools\\Administrator::addClient", "logChange");
        Hooks::registerHook("code", "client_audit", "end", "FormTools\\Administrator::adminUpdateClient", "logChange");
        Hooks::registerHook("code", "client_audit", "end", "FormTools\\Clients::updateClient", "logChange");
        Hooks::registerHook("code", "client_audit", "end", "FormTools\\Clients::disableClient", "logChange");
        Hooks::registerHook("code", "client_audit", "end", "FormTools\\Clients::deleteClient", "logChange");

        // called when a form (main tab) or the View is updated
        Hooks::registerHook("code", "client_audit", "end", "FormTools\\Views::updateView", "logChange");
        Hooks::registerHook("code", "client_audit", "end", "FormTools\\Forms::updateFormMainTab", "logChange");
        Hooks::registerHook("code", "client_audit", "start", "FormTools\\Forms::deleteForm", "logChange");

        // lastly, create a default, hidden entry of all data - account contents, settings and permissions
        // for each client. This is used as a basis for comparison for the NEXT time something changed
        $this->initClientLogs();

        return array(true, "");
    }


    public function uninstall($module_id)
    {
        $db = Core::$db;

        $db->query("DROP TABLE {PREFIX}module_client_audit_accounts");
        $db->execute();

        $db->query("DROP TABLE {PREFIX}module_client_audit_account_settings");
        $db->execute();

        $db->query("DROP TABLE {PREFIX}module_client_audit_changes");
        $db->execute();

        $db->query("DROP TABLE {PREFIX}module_client_audit_client_permissions");
        $db->execute();

        return array(true, "");
    }

    /**
     * Our one hook function to rule them all. This is executed for all registered code hooks for the
     * module.
     *
     * @param $info
     */
    public function logChange($info)
    {
        $calling_function = $info["form_tools_hook_info"]["function_name"];

        switch ($calling_function) {
            case "FormTools\\User->login":
                if ($info["account_info"]["account_type"] == "admin") {
                    return;
                }
                Changes::insertChangeRow("login", $info["account_info"]["account_id"]);
                break;

            // bit hacky. The ft_logout_user hook doesn't pass the account ID, so we pull it from sessions
            case "FormTools\\User->logout":
                if (!Sessions::exists("account") || !Sessions::exists("account.account_id")) {
                    return;
                }
                $account = Sessions::get("account");
                $account_id = $account["account_id"];
                $account_type = $account["account_type"];
                if ($account_type == "admin") {
                    return;
                }
                Changes::insertChangeRow("logout", $account_id);
                break;

            case "FormTools\\Administrator::addClient":
                $change_id = Changes::insertChangeRow("account_created", $info["new_user_id"]);
                Changes::updateAccountChangelog($info["new_user_id"], $change_id);
                $change_id = Changes::insertChangeRow("permissions", $info["new_user_id"], true);
                Changes::updateAccountPermissions($info["new_user_id"], $change_id);
                break;

            case "FormTools\\Administrator::adminUpdateClient":
                $client_id = $info["infohash"]["client_id"];

                if ($info["tab_num"] == 1 || $info["tab_num"] == 2) {
                    $old_account_info = Changes::getLastAccountState($client_id); // false if there's no previous state

                    if (empty($old_account_info) || Changes::accountHasChanged($client_id, $old_account_info)) {
                        $change_id = Changes::insertChangeRow("admin_update", $client_id);
                        Changes::updateAccountChangelog($client_id, $change_id, $old_account_info);
                    }
                }
                if ($info["tab_num"] == 3) {
                    // log the permissions change iff the content changed
                    $new_permissions = Changes::getSerializedPermissionString($client_id);
                    $old_permissions = Changes::getLastAccountPermissions($client_id);

                    if ($new_permissions != $old_permissions) {
                        $change_id = Changes::insertChangeRow("permissions", $client_id);
                        Changes::updateAccountPermissions($client_id, $change_id);
                    }
                }
                break;

            case "FormTools\\Clients::updateClient":
                $old_account_info = Changes::getLastAccountState($info["account_id"]);
                $client_id = $info["account_id"];
                if (Changes::accountHasChanged($client_id, $old_account_info)) {
                    $change_id = Changes::insertChangeRow("client_update", $client_id);
                    Changes::updateAccountChangelog($client_id, $change_id, $old_account_info);
                }
                break;

            case "FormTools\\Clients::disableClient":
                $old_account_info = Changes::getLastAccountState($info["account_id"]);
                $change_id = Changes::insertChangeRow("account_disabled_from_failed_logins", $info["account_id"]);
                Changes::updateAccountChangelog($info["account_id"], $change_id, $old_account_info);
                break;

            case "FormTools\\Clients::deleteClient":
                Changes::insertChangeRow("account_deleted", $info["account_id"]);
                break;

            // assorted events within FT that could change the permissions on any of the client accounts
            case "FormTools\\Views::updateView":
            case "FormTools\\Forms::updateFormMainTab":
            case "FormTools\\Forms::deleteForm":
                $client_ids = Changes::getLoggedClientAccountsWithPermissions();
                foreach ($client_ids as $client_info) {
                    $client_id = $client_info["account_id"];
                    $old_permissions = Changes::getLastAccountPermissions($client_id);
                    $new_permissions = Changes::getSerializedPermissionString($client_id);
                    if ($old_permissions != $new_permissions) {
                        $change_id = Changes::insertChangeRow("permissions", $client_id);
                        Changes::updateAccountPermissions($client_id, $change_id);
                    }
                }
                break;

        }
    }


    private function initClientLogs()
    {
        $clients = Clients::getList();

        foreach ($clients as $client_info) {
            $account_id = $client_info["account_id"];

            $change_id = Changes::insertChangeRow("account_created", $account_id, true);
            Changes::updateAccountChangelog($account_id, $change_id);

            $change_id = Changes::insertChangeRow("permissions", $account_id, true);
            Changes::updateAccountPermissions($account_id, $change_id);
        }

    }
}
