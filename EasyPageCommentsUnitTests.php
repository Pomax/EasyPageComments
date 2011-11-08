<?php
	// turn on error reporting
	date_default_timezone_set('America/Vancouver');
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);

  // include our test class
  require_once("EasyPageComments.php");

  // test function
  function equal($a, $b) { return ($a === $b) ? "passed" : "FAILED"; }


// ====== UNIT TEST: EPC_Schema_entity
   $EPC_test_var = new EPC_Schema_Entity("a", "b", "c");
   echo "EPC_Schema_Entity name test: "    . equal($EPC_test_var->name,     "a") . "\n";
   echo "EPC_Schema_Entity type test: "    . equal($EPC_test_var->type,     "b") . "\n";
   echo "EPC_Schema_Entity default test: " . equal($EPC_test_var->default, "c") . "\n";
// ====== UNIT TEST


// ====== UNIT TEST: EPC_Schema
   $EPC_test_var = EPC_Schema::getSchema();
   echo "Schema entry count (should be 8): "  . equal(count($EPC_test_var), 8)                   . "\n";
   echo "Database schema entry {id}: "        . equal($EPC_test_var["id"]->name, "id")           . "\n";
   echo "Database schema entry {page}: "      . equal($EPC_test_var["page"]->name, "page")       . "\n";
   echo "Database schema entry {name}: "      . equal($EPC_test_var["name"]->name, "name")       . "\n";
   echo "Database schema entry {email}: "     . equal($EPC_test_var["email"]->name, "email")     . "\n";
   echo "Database schema entry {timestamp}: " . equal($EPC_test_var["timestamp"]->name, "timestamp") . "\n";
   echo "Database schema entry {body}: "      . equal($EPC_test_var["body"]->name, "body")       . "\n";
   echo "Database schema entry {replyto}: "   . equal($EPC_test_var["replyto"]->name, "replyto") . "\n";
   echo "Database schema entry {notify}: "    . equal($EPC_test_var["notify"]->name, "notify")   . "\n";

//   $expected = "CREATE TABLE comments(id INTEGER PRIMARY KEY AUTOINCREMENT DEFAULT '0', page TEXT DEFAULT 'EasyPageComments', name TEXT DEFAULT '', email TEXT DEFAULT '', timestamp INTEGER DEFAULT '0', body TEXT DEFAULT '', replyto INTEGER DEFAULT '0')";
//   $expected = "CREATE TABLE comments(id INTEGER PRIMARY KEY AUTOINCREMENT DEFAULT '0', page TEXT DEFAULT 'EasyPageComments', name TEXT DEFAULT '', email TEXT DEFAULT '', timestamp INTEGER DEFAULT '0', body TEXT DEFAULT '', replyto INTEGER DEFAULT '0', notify INTEGER DEFAULT '0')";
//   echo "Database creation: " . equal(EPC_Schema::create("sqlite:EPC_test.db"), $expected) . "\n";
//   unlink("EPC_test.db");

// TODO: add in unit testing for database upgrading
//
//   copy("EPC_unit_test_upgrade.db","backup.db");
//   EPC_Schema::upgrade("sqlite:EPC_unit_test_upgrade.db");

// ====== UNIT TEST



?>
