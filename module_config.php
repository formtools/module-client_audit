<?php

$STRUCTURE = array();
$STRUCTURE["tables"] = array();
$STRUCTURE["tables"]["module_client_audit_accounts"] = array(
    array(
        "Field"   => "change_id",
        "Type"    => "mediumint(8) unsigned",
        "Null"    => "NO",
        "Key"     => "PRI",
        "Default" => ""
    ),
    array(
        "Field"   => "changed_fields",
        "Type"    => "mediumtext",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "account_status",
        "Type"    => "enum('active','disabled','pending')",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => "disabled"
    ),
    array(
        "Field"   => "ui_language",
        "Type"    => "varchar(50)",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => "en_us"
    ),
    array(
        "Field"   => "timezone_offset",
        "Type"    => "varchar(4)",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "sessions_timeout",
        "Type"    => "varchar(10)",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => "30"
    ),
    array(
        "Field"   => "date_format",
        "Type"    => "varchar(50)",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => "M jS, g:i A"
    ),
    array(
        "Field"   => "login_page",
        "Type"    => "varchar(50)",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => "client_forms"
    ),
    array(
        "Field"   => "logout_url",
        "Type"    => "varchar(255)",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "theme",
        "Type"    => "varchar(50)",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => "default"
    ),
    array(
        "Field"   => "swatch",
        "Type"    => "varchar(255)",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "menu_id",
        "Type"    => "mediumint(8) unsigned",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "first_name",
        "Type"    => "varchar(100)",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "last_name",
        "Type"    => "varchar(100)",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "email",
        "Type"    => "varchar(200)",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "username",
        "Type"    => "varchar(50)",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "password",
        "Type"    => "varchar(50)",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    )
);
$STRUCTURE["tables"]["module_client_audit_account_settings"] = array(
    array(
        "Field"   => "change_id",
        "Type"    => "mediumint(9)",
        "Null"    => "NO",
        "Key"     => "PRI",
        "Default" => ""
    ),
    array(
        "Field"   => "setting_name",
        "Type"    => "varchar(255)",
        "Null"    => "NO",
        "Key"     => "PRI",
        "Default" => ""
    ),
    array(
        "Field"   => "setting_value",
        "Type"    => "mediumtext",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => ""
    )
);
$STRUCTURE["tables"]["module_client_audit_changes"] = array(
    array(
        "Field"   => "change_id",
        "Type"    => "mediumint(8) unsigned",
        "Null"    => "NO",
        "Key"     => "PRI",
        "Default" => ""
    ),
    array(
        "Field"   => "change_date",
        "Type"    => "datetime",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "change_type",
        "Type"    => "enum('account_created','account_deleted','admin_update','client_update','account_disabled_from_failed_logins','permissions','login','logout')",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "status",
        "Type"    => "enum('hidden','visible')",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => "visible"
    ),
    array(
        "Field"   => "account_id",
        "Type"    => "mediumint(9)",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => ""
    )
);
$STRUCTURE["tables"]["module_client_audit_client_permissions"] = array(
    array(
        "Field"   => "change_id",
        "Type"    => "mediumint(8) unsigned",
        "Null"    => "NO",
        "Key"     => "PRI",
        "Default" => ""
    ),
    array(
        "Field"   => "added_views",
        "Type"    => "mediumtext",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "removed_views",
        "Type"    => "mediumtext",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "added_forms",
        "Type"    => "mediumtext",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "removed_forms",
        "Type"    => "mediumtext",
        "Null"    => "YES",
        "Key"     => "",
        "Default" => ""
    ),
    array(
        "Field"   => "permissions",
        "Type"    => "mediumtext",
        "Null"    => "NO",
        "Key"     => "",
        "Default" => ""
    )
);

$HOOKS = array(
    array(
        "hook_type"       => "code",
        "action_location" => "main",
        "function_name"   => "FormTools\\User->login",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "main",
        "function_name"   => "FormTools\\User->logout",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Administrator::addClient",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Administrator::adminUpdateClient",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Clients::updateClient",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Clients::disableClient",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Clients::deleteClient",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Views::updateView",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Forms::updateFormMainTab",
        "hook_function"   => "logChange",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "start",
        "function_name"   => "FormTools\\Forms::deleteForm",
        "hook_function"   => "logChange",
        "priority"        => "50"
    )
);


$FILES = array(
    "code/",
    "code/Changes.class.php",
    "code/Module.class.php",
    "css/",
    "css/styles.css",
    "images/",
    "images/icon_client_audit.gif",
    "lang/",
    "lang/en_us.php",
    "smarty_plugins/",
    "smarty_plugins/function.display_deleted_account_name.php",
    "templates/",
    "templates/details.tpl",
    "templates/help.tpl",
    "templates/index.tpl",
    "details.php",
    "help.php",
    "index.php",
    "library.php",
    "LICENSE",
    "module_config.php",
    "README.md"
);
