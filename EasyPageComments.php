<?php

/**
 * EasyPageComments package
 * (c) Mike "Pomax" Kamermans, 2011
 * http://github.com/Pomax/EasyPageComments
 */

class EasyPageComments
{
  var $VERSION = "2011-11-06-10-41";

// ------------------------------------
//    MODIFY TO FIT PERSONAL NEEDS
// ------------------------------------

  // this is used to mark your own posts differently from everyone else's
  var $admin_alias = "Pomax";

  // what should be the security question for this page?
  var $security_question = "If I write a one, and then a zero, which number did I write?";

  // what are valid security answers for this page?
  var $security_answers = array("10", "ten", "１０");

  // should comment threads get an auto-generated RSS feed button?
  var $rss = true;

  // should you be notified by email when someone posts a comment?
  var $notify = true;

  // what's the email address to send those notifications to?
  var $to = "mymail@mydomain.com";

  // what's the subject you'd like?
  var $subject = "[EasyPageComments] page comment posted";

  // where can the sqlite database be found
  var $db_dir = "./sqlite";

  // if EasyPageComments.php is not in the same directory as the
  // calling script, this value must be overriden in the constructor.
  var $EPC_path = "";

// ------------------------------------
//    DO NOT MODIFY BEYOND THIS POINT
// ------------------------------------

  var $db_handle = -1;
  var $thispage = "";
  var $loc = "";
  var $build_db = false;
  var $from_javascript = false;

  var $failed_post = false;
  var $failures = array();
  var $values = array();

  var $current_user_name = false;
  var $current_user_email = false;
  var $trusted = false;

  /**
   * The constructor tries to figure out for
   * which page EasyPageComments is running.
   */
  function __construct($parameters=array()) {
    $this->thispage =& $_SERVER["PHP_SELF"];
    $this->loc =& $_SERVER["SCRIPT_URI"];
    // set values based on passed parameters (if any were passed)
    if(isset($parameters["name"]))  { $this->current_user_name  = $parameters["name"]; }
    if(isset($parameters["email"])) { $this->current_user_email = $parameters["email"]; }
    if(isset($parameters["path"])) { $this->EPC_path = $parameters["path"]; }
    // is this a trusted user?
    $this->trusted = $this->current_user_name!==false && $this->current_user_email!==false;
  }

  /**
   * This function ensures the right database
   * is being used for comment read/writing.
   */
  function verify_db($db_name="comments") {
    $db_location = $this->db_dir . "/" . $db_name . ".db";
    $build_db = (!file_exists($db_location));
    $this->db_handle = "sqlite:" . $db_location;
    if($build_db) { EPC_Schema::create($this->db_handle); }
  }

  // ------

  /**
   * A simple safifying function.
   */
  function make_safe($string) {
    $string = str_replace("<p>","",$string);
    $string = str_replace("</p>","\n\n",$string);
    $string = str_replace("<br>","\n",$string);
    $string = str_replace("<br/>","\n",$string);
    $string = str_replace("'","&#39;",$string);
    $string = str_replace('"',"&#34;",$string);
    $string = str_replace("<","&lt;",$string);
    $string = str_replace(">","&gt;",$string);
    return $string; }

  /**
   * Turn a string from safified into
   * a reasobly readable string.
   */
  function make_readable($string) {
    $string = preg_replace_callback("/(&#[0-9]+;)/",
                                    create_function('$m', 'return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");'),
                                    $string);
    $string = str_replace("<","&lt;",$string);
    $string = str_replace(">","&gt;",$string);
    $string = str_replace("\n","<br>",$string);
    return $string; }

  /**
   * We validate email address using the only
   * correct regular expression to email addresses.
   */
  function valid_email($email) {
    $regexp = "/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i";
    return preg_match($regexp, $email) == 1; }

  /**
   * Test whether a container has all required keys.
   * This is used for checking $_GET and $_POST
   */
  function isset_for($CONTAINER, $keys) {
    foreach($keys as $key) {
      if(!isset($CONTAINER[$key])) return false; }
    return true; }

  /**
   * Test whether the security question was correctly answered
   */
  function correctAnswer($given) {
    // trusted users automatically pass
    if($this->trusted) return true;
    // untrusted users must have provided the right answer
    foreach($this->security_answers as $answer) {
      if($answer==$given) return true; }
    return false; }

// ------

  /**
   * Handling for POST request
   */
  function processPOST()
  {
    // if these values are not set, EasyPageComments.php was not called by us!
    $requirements = array("name", "email", "body", "reply");
    if(!$this->trusted) { $requirements[] = "security"; }

    if (!$this->isset_for($_POST, $requirements)) {
      $html  = "<div class=\"EPC-response\">\n";
      $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
      $html .= "<div class=\"EPC-response-title\">Missing fields</div>\n";
      $html .= "<p>Could not process form: required element values are missing.</p>";
      $html .= "</div> <!-- EPC-response-text -->\n";
      print $html;
      return;
    }

    // if we're calling this from javascript, responses must always be printed.
    $this->from_javascript = isset($_GET["caller"]) && $_GET["caller"]=="JavaScript";

    // get the values we need
    $name = $this->make_safe($_POST["name"]);
    $email = trim($_POST["email"]);
    $timestamp = time();
    $body = $this->make_safe($_POST["body"]);
    $answer = trim($_POST["security"]);
    $replyto = intval(str_replace("EasyPageComment","",$_POST["reply"]));

    // make sure to cache the values in case something fails (but don't cache the security answer)
    $this->values = array("name"=>$name, "email"=>$email, "body"=>$body, "replyto"=>$replyto);

    // default page override?
    if($_POST["page"]) { $this->thispage = $this->make_safe($_POST["page"]); }

    $html = "<div class=\"EPC-response\">\n";

    // processing is contingent on the security question
    // being answered correctly. If it's wrong, we don't
    // even bother looking at the rest of the data.
    if($this->correctAnswer($answer))
    {
      if($name!=""&& $this->valid_email($email) && $body !="")
      {
        $success = true;
        $this->verify_db($this->thispage);
        $dbh = new PDO($this->db_handle);

        // insert the comment
        // TODO: add the "notify" column
        $insert  = 'INSERT INTO comments (page, name, email, timestamp, body, replyto) ';
        $insert .= 'VALUES ("' . $this->thispage . '", "' . $name . '", "' . $email  . '", "' . $timestamp . '", "' . $body . '", ' . $replyto . ')';
        $result = $dbh->exec($insert);
        $dbh = null;
        $success = ($result==1);

        /**
         * Posting succeeded!
         */
        if($success) {

          $html .= '<input id="EPC-status" name="'.$this->thispage.'" value="SUCCESS">'."\n";

          // if notification is desired, send out a mail
          // (but not for our own messages)
          if($name!==$this->admin_alias && $this->notify)
          {
            // get the id for this post after insertion
            $dbh = new PDO($this->db_handle);
            $id = 0;
            $query = "SELECT * FROM comments WHERE page = '".$this->thispage."' AND name = '$name' AND email = '$email' AND timestamp = $timestamp";
            foreach($dbh->query($query) as $data) { $id = $data["id"]; }
            $dbh = null;

            // quick alias
            $page =& $this->thispage;
            $loc =& $this->loc;

            // compose message
            $message = "A comment was posted on $loc by $name ($email):\n";
            $message .= "\n---\n$body\n\n";
            $message .= "click <a href=\"$loc#${page}-comment-$id\">here</a> to view this comment online\n";

            // set up headers
            $headers = "From: EasyPageComment-Mailer@" . $_SERVER["HTTP_HOST"] . "\r\n" .
                       "Reply-To: $name <$email>\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // and mail everything off
            mail($this->to, $this->subject, $message, $headers);
          }

          // TODO: send mails to all users that want to be notified of downstream replies
          /*
            code goes here
          */
        }

        /**
         * Posting failed, despite all values conforming to acceptable formats
         */
        else
        {
          $message = "An error occurred while trying to save an EasyPageComments comment for ".$this->loc."\n";
          $message = "Message data:\n";
          $message = "NAME   : $name\n";
          $message = "EMAIL  : $email\n";
          $message = "COMMENT: $body\n";

          $headers = "From: EasyPageComment-Mailer@" . $_SERVER["HTTP_HOST"] . "\r\n" .
                     "Reply-To: $name <$email>\r\n" .
                     "X-Mailer: PHP/" . phpversion();

          // notification is not optional here. You will receive error mails.
          mail($this->to, "EasyPageComments error", $message, $headers);

          $html .= '<input id="EPC-status" name="'.$this->thispage.'" value="ERROR">'."\n";
        }
      }

      /**
       * Posting could not be performed - data was missing, or of the wrong format
       */
      else {
        $this->failed_post = true;
        $html .= '<input id="EPC-status" name="'.$this->thispage.'" value="FAILED">';
        if($name=="") {
          $this->failures["name"] = "You left your name blank";
          $html .= '<input name="name" value="You left your name blank">'."\n"; }
        if($email=="") {
          $this->failures["email"] = "You left your email address blank";
          $html .= '<input name="email" value="You left your email address blank">'."\n"; }
        elseif(!$this->valid_email($email)) {
          $this->failures["email"] = "You filled in an email address, but it wasn't a valid address";
          $html .= '<input name="email" value="You filled in an email address, but it wasn\'t a valid address">'."\n"; }
        if($body=="") {
          $this->failures["body"]  ="You did not write a comment";
          $html .= '<input name="body" value="You did not write a comment">'."\n"; }
      }
    }

    /**
     * Posting could not be performed - the security question was answered incorrectly
     */
    else {
      $this->failed_post = true;
      $this->failures["security"] = "You did not answer the security question correctly";
      $html .= '<input id="EPC-status" name="'.$this->thispage.'" value="FAILED">' . "\n";
      $html .= '<input name="security" value="You did not answer the security question correctly">'."\n";
    }

    // if we're calling from javascript, print response.
    if($this->from_javascript) { print $html; }
  }

// ------

  /**
   * Handling for GET requests
   */
  function processGET() {
    if(isset($_GET["getList"])) {
      print $this->createCommentsList($_GET["getList"]); }
    elseif(isset($_GET["getForm"])) {
      print $this->createCommentForm($_GET["getForm"], true); }
    elseif(isset($_GET["getRSS"]) && isset($_GET["url"])) {
      print $this->createRSSfeed($_GET["getRSS"], $_GET["url"]); }}

// ------

  /**
   * This function builds the HTML for the
   * comment list, and then prints it to output.
   */
  function createCommentsList($pagename=false)
  {
    $entrylist = array();
    if($pagename!==false) { $this->thispage = $pagename; }

    $this->verify_db($pagename);
    $dbh = new PDO($this->db_handle);
    foreach($dbh->query("SELECT * FROM comments WHERE page LIKE '".$this->thispage."' ORDER BY replyto") as $data)
    {
      $id = $data['id'];

      $html = "\t<div class=\"EPC-entry";
      if($data['name']==$this->admin_alias) { $html .= " EPC-owner-comment"; }
      $html .= "\" id=\"EasyPageComment$id-" . $data['replyto'] . "\">\n";
      $html .= "\t\t<a name=\"$pagename-comment-$id\"></a>\n";
      $html .= "\t\t<div class=\"EPC-entry-name\">" . $data['name'] . "</div>\n";

      // convert timestamp to string
      $t =& $data["timestamp"];
      $timestamp = date("l, F j", $t) . "<sup>" . date("S", $t) . "</sup>" . date(" Y - g:i a (", $t) . "GMT" . substr(date("P", $t),0,1) . substr(date("P", $t),2,1) . ")";

      $html .= "\t\t<div class=\"EPC-entry-time\"><a href=\"#$pagename-comment-$id\">$timestamp</a></div>\n";
      $html .= "\t\t<div class=\"EPC-entry-comment\">" . str_replace("\n","<br/>",$data['body']) . "</div>\n";

      $onclick   = "document.querySelector('#EPC-$pagename input[name=reply]').value='EasyPageComment$id';";
      $onclick  .= "document.querySelector('#EPC-$pagename .EPC-comment-type').innerHTML='reply';";
      if($this->trusted) { $onclick .= "document.querySelector('#EPC-$pagename .EPC-form-comment textarea').focus();"; }
      else  { $onclick .= "document.querySelector('#EPC-$pagename .EPC-form-name input').focus();"; }

      $html .= "<div class=\"EPC-entry-reply\"><a href=\"#EPC-form-$pagename\" onclick=\"$onclick\">reply</a></div>\n";
      $html .= "\t</div> <!-- EasyPageComments entry -->\n";

      $entry = array("id"=>$id, "parent"=>$data["replyto"], "html"=>$html, "depth"=>1);
      $entrylist[] = $entry;
    }
    $dbh = null;

    // set up thread baed on hierarchical topology
    for($i=count($entrylist)-1; $i>=0; $i--) {
      $e = $entrylist[$i];
      $parent = $e["parent"];
      if($parent==0) { continue; }
      for($j=$i-1; $j>=0; $j--) {
        $e2 =& $entrylist[$j];
        if($e2["id"]==$parent) {
          if(!isset($e2["children"])) {
            $e2["children"] = array(); }
          $e2["children"][] = $e;
          break; }
        $entrylist[$i]=null; }}

    // form HTML for threaded topology
    $html  = "<div class=\"EPC-list\">\n";
    if($this->rss) {
      $html .= "<div class=\"EPC-RSS-link\"><a href=\"" . $this->EPC_path . "EasyPagecomments.php?getRSS=${pagename}&url=" . $this->page_url(). "\" title=\"RSS feed for this comment thread\">";
      $html .= "<img src=\"rss.png\" alt=\"RSS feed\"/></a></div>\n"; }
    foreach($entrylist as $entry) {
      if($entry==null) continue;
      if($entry["parent"]==0) {
        $html .= $this->stringwalk($entry, 0); }}
    $html .= "</div> <!-- EasyPageComments list -->\n";

    print $html;
  }

  /**
   * This is a helper function for the comment list
   * creation function, and recursively walks through
   * a "node set", to build the nested HTML in a
   * sensible (depth-first) order.
   */
  function stringwalk($parent, $depth)
  {
    $html = "<div class=\"EPC-depth\">\n";
    $html .= $parent["html"];
    if(isset($parent["children"])) {
      $children =& $parent["children"];
      for($i=count($children)-1; $i>=0; $i--) {
        $html .= $this->stringwalk($children[$i], $depth+1); }}
    $html .= "</div><!-- EPC-depth $depth -->\n";
    return $html;
  }

  /**
   * This function builds the HTML for the
   * comment form, and then prints it to output.
   */
  function createCommentForm($page=false, $asynchronous=false) {
    // set up an anchor so that if there is an error in the
    // form posting, we jump straight to the post form.
    if($this->failed_post) {?>
    <a name="EasyPageComment-form-<?php echo $page; ?>"></a><?php
    }
    ?>
    <a name="EPC-form-<?php echo $page; ?>"></a>
    <form id="EPC-<?php echo $page; ?>" class="EPC-form" action=".#EasyPageComment-form-<?php echo $page; ?>" method="POST"><?php

    // only write a comment form if we know for which comment thread it is!
    if($page!==false) { ?>
      <input class="EPC-form-page" type="hidden" name="page" value="<?php echo $page; ?>">
<?php } ?>
      <input class="EPC-form-reply" type="hidden" name="reply" value="0">
      <div class="EPC-error-message"><?php
        if($this->failed_post) { echo "There are some problems with your comment that you need to fix before posting."; }
      ?></div>

      <div class="EPC-form-name">
        <label>Your name:</label>
        <?php
        // Is this a trusted user? If so, fill in the name automatically.
        if($this->trusted) { ?>
          <input type="hidden" name="name" value="<?php echo $this->current_user_name; ?>">
          <span class="EPC-user-name"><?php echo $this->current_user_name;?></span><?php
        }
        // If this is not a trusted user, make them fill in their name.
        else { ?>
        <input type="text" name="name"<?php
          // if posting failed, showing the form on the same page-call should
          // mark errors, and fill in elements with what the user had written.
          if($this->failed_post) {
            echo 'value="' . $this->values["name"]. '"';
            if(isset($this->failures["name"])) {
              echo ' class="EPC-error" title="'.$this->failures["name"].'"';
            }
          }
          ?>></input>
      <?php } ?>
      </div>

      <div class="EPC-form-email">
        <label>Your email:</label>
        <?php
        // Is this a trusted user? If so, fill in the name automatically.
        if($this->trusted) { ?>
          <input type="hidden" name="email" value="<?php echo $this->current_user_email; ?>">
          <span class="EPC-user-email"><?php echo $this->current_user_email;?></span><?php
        }
        // If this is not a trusted user, make them fill in their name.
        else { ?>
        <input type="text" name="email"<?php
          // if posting failed, showing the form on the same page-call should
          // mark errors, and fill in elements with what the user had written.
          if($this->failed_post) {
            echo 'value="' . $this->values["email"]. '"';
            if(isset($this->failures["email"])) {
              echo ' class="EPC-error" title="'.$this->failures["email"].'"';
            }
          }
          ?>></input>
      <?php } ?>
      </div>

      <div class="EPC-form-comment">
        <label>Your <span class="EPC-comment-type">comment or question</span>:</label>
        <textarea name="body"<?php
          // if posting failed, showing the form on the same page-call should
          // mark errors, and fill in elements with what the user had written.
          if($this->failed_post && isset($this->failures["body"])) {
            echo ' class="EPC-error" title="'.$this->failures["body"].'"';
          }?>><?php
          if($this->failed_post) {
            echo $this->values["body"];
          }?></textarea>
      </div>

      <div class="EPC-security">
        <?php
        // If this is not a trusted user, we need the security question.
        if(!$this->trusted) {?>
        <div class="EPC-security-question"><?php echo $this->security_question; ?></div>
        <input type="text" name="security"<?php
          // if posting failed, showing the form on the same page-call should
          // mark errors, and fill in elements with what the user had written.
          //
          // this element already has a class attribute, so we need slighlty
          // different handling for it.
          if($this->failed_post) {
            echo 'value="' . $this->values["security"]. '"';
            if(isset($this->failures["security"])) {
              echo ' class="EPC-security-answer EPC-error" title="'.$this->failures["security"].'"';
            } else {
              echo ' class="EPC-security-answer"';
            }
          } else {
            echo ' class="EPC-security-answer"';
          }?>></input>
        <?php } ?>
      </div>

      <div class="EPC-form-buttons">
        <input class="EPC-form-clear" type="reset" name="clear" value="clear" onclick="<?php
          echo "document.querySelector('#EPC-$page input[name=reply]').value='0';";
          echo "document.querySelector('#EPC-$page .EPC-comment-type').innerHTML='comment or question';";
          if($this->trusted) { echo "document.querySelector('#EPC-$page .EPC-form-comment textarea').focus();"; }
          else  { echo "document.querySelector('#EPC-$page .EPC-form-name input').focus();"; }
        ?>"></input>
        <input class="EPC-form-submit" type="<?php
          // If we're running on javascript, the submit button should not
          // actually submit the form, but call a javascript function. As
          // such, turn it into an <input type='button'> instead.
          echo ($asynchronous && $page!==false ? "button" : "submit");
        ?>" name="submit" value="post"<?php
          if($asynchronous && $page!==false) {
            echo ' onclick="EasyPageComments.post(\''.$page.'\'' . ($this->trusted ? ', true' : '') . ')"'; }
        ?>></input>
      </div>
    </form>
    <?php
  }

  /**
   * This function gets the page URL for RSS <link> elements
   */
  function page_url() {
   $pageURL = 'http';
   if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
   $pageURL .= "://";
   if ($_SERVER["SERVER_PORT"] != "80") { $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"]; }
   else { $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]; }
   return preg_replace("/\?.+/","",$pageURL);
  }

  /**
   * This function generates an RSS feed from the comment section
   */
  function createRSSfeed($pagename=false, $url=false)
  {
    $entrylist = array();
    if($pagename!==false) { $this->thispage = $pagename; }
    if($url===false) { return; }

    $this->verify_db($pagename);
    $rss  = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    $rss .= '<rss version="0.91">' . "\n";
    $rss .= "  <channel> \n";
    $rss .= "    <title>".$this->thispage." comment feed</title>\n";
    $rss .= "    <ttl>150</ttl>\n";

    $dbh = new PDO($this->db_handle);
    foreach($dbh->query("SELECT * FROM comments ORDER BY id DESC") as $data)
    {
      $t =& $data["timestamp"];
      $timestamp = date("l, F j", $t) . "<sup>" . date("S", $t) . "</sup>" . date(" Y - g:i a (", $t) . "GMT" . substr(date("P", $t),0,1) . substr(date("P", $t),2,1) . ")";

      $rss .= "    <item>\n";
      $rss .= "      <title>Comment by " .$this->make_readable($data['name']). " (" . $timestamp. ")</title>\n";
      $rss .= "      <link>" . $url . "#${pagename}-comment-".$data["id"]."</link>\n";
      $rss .= "      <description> " . $this->make_readable($data['body']) . "</description>\n";
      $rss .= "    </item>\n";
    }
    $dbh = null;
    $rss .= "  </channel>\n";
    $rss .= "</rss>\n";

    print $rss;
    // we terminate after an RSS request. No additional content may be generated.
    exit(0);
  }

}

/**
 * Class for database schema columns
 */
class EPC_Schema_Entity {
  var $name, $type, $default;
  function __construct($column_name, $column_type, $default) {
    $this->name = $column_name;
    $this->type = $column_type;
    $this->default = $default; }
  function toString() {
    return $this->name . " " . $this->type . " DEFAULT '" . $this->default . "'"; }}

/**
 * Class for the EasyPageComments database schema
 */
class EPC_Schema
{
  /**
   * The current database schema
   * (initialised when first requested)
   */
  static $schema = array();

  /**
   * add an EPC_Schema_Entity to the schema collection
   */
  static function addSchemaEntry($name, $type, $default)
  {
    EPC_SCHEMA::$schema[$name] = new EPC_Schema_Entity($name, $type, $default);
  }

  /**
   * Get the current database schema
   */
  static function getSchema()
  {
    if(count(EPC_SCHEMA::$schema)==0) {

      // Schema used in v2011-11-06-10-41 and earlier

      // unique entry identifier
      EPC_SCHEMA::addSchemaEntry("id","INTEGER PRIMARY KEY AUTOINCREMENT",0);
      // comment collection name
      EPC_SCHEMA::addSchemaEntry("page","TEXT","EasyPageComments");
      // user name
      EPC_SCHEMA::addSchemaEntry("name","TEXT","");
      // user email
      EPC_SCHEMA::addSchemaEntry("email","TEXT","");
      // unix timestamp for when the message is recorded
      EPC_SCHEMA::addSchemaEntry("timestamp","INTEGER",0);
      // comment text
      EPC_SCHEMA::addSchemaEntry("body","TEXT","");
      // is this entry a reply? 0 means no, 1+ means it's a reply to id=replyto
      EPC_SCHEMA::addSchemaEntry("replyto","INTEGER",0);

      /*
        TODO: add in email notifications to individual posters when
              someone replies downstream from their comment.

        // email notification; 0 means no, 1 means yes (added in v2011-11-06-10-41)
        EPC_SCHEMA::addSchemaEntry("notify","INTEGER",0);
      */
    }
    return EPC_SCHEMA::$schema;
  }

  /**
   * Build the database from schema
   */
  static function create($db_handle)
  {
    // build create statement
    $create = "CREATE TABLE comments(";
    foreach(EPC_Schema::getSchema() as $key => $entry) {
      $create .= $entry->toString() . ", "; }
    $create = substr($create, 0, strlen($create)-2) . ")";

    // execute build
    $dbh = new PDO($db_handle);
    $stmt = $dbh->prepare($create);
    $stmt->execute();
    $dbh = null;

    // return statement used for creation
    return $create;
  }

  /**
   * Upgrade an older database to the current schema
   */
  static function upgrade($db_handle)
  {
/*
    // backup the original
    $dbh = new PDO($db_handle);
    $stmt = $dbh->prepare("CREATE TABLE comments_backup AS SELECT * FROM comments");
    $stmt->execute();

    // drop the original and create the new table layout
    $stmt = $dbh->prepare("DROP TABLE comments");
    $stmt->execute();
    $dbh = null;
    EPC_Schema::create($db_handle);

    // copy the old data back in
    // -- FIXME: this is not how it works in SQLITE, it'll complain about column mismatching
    $dbh = new PDO($db_handle);
    $stmt = $dbh->prepare("SELECT * INTO comments FROM comments_backup");
    $stmt->execute();

    // and delete the backup
    $stmt = $dbh->prepare("DROP TABLE comments_backup");
    $stmt->execute();
    $dbh = null;
*/
  }
}

/**
  The preceding code sets up the EasyPageComments class,
  as well as dependency classes.

  The following code actually makes use of them, by building
  an EasyPageComments object, and making it do its thing
  based on whether a page was requested via GET or POST.
**/

$EasyPageComments = new EasyPageComments(isset($EasyPageComments) && is_array($EasyPageComments) ? $EasyPageComments : array());

// deal with EPC-specific POST/GET requests
if(isset($_SERVER["REQUEST_METHOD"])) {
  if($_SERVER["REQUEST_METHOD"]=="POST") {
    $EasyPageComments->processPost(); }
  elseif($_SERVER["REQUEST_METHOD"]=="GET") {
    $EasyPageComments->processGET(); }}

/**
  After this point, control is returned to whatever included
  the EasyPageComments.php file.
  If EasyPageComments.php was called directly, this script stops here.
**/
?>