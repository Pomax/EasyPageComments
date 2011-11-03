<?php

/**
 * EasyPageComments package
 * (c) Mike "Pomax" Kamermans, 2011
 * http://github.com/Pomax/EasyPageComments
 */

class EasyPageComments
{

// ------------------------------------
//    MODIFY TO FIT PERSONAL NEEDS
// ------------------------------------


  // this is used to mark your own posts differently from everyone else's
  var $admin_alias = "Pomax";

  // what should be the security question for this page?
  var $security_question = "If I write a one, and then a zero, which number did I write?";

  // what are valid security answers for this page?
  var $security_answers = array("10", "ten", "１０");

  // should you be notified by email when someone posts a comment?
  var $notify = false;

  // what's the email address to send those notifications to?
  var $to = "mymail@mydomain.com";

  // what's the subject you'd like?
  var $subject = "[EasyPageComments] page comment posted";

  // where can the sqlite database be found
  var $db_dir = "./sqlite";

// ------------------------------------
//    DO NOT MODIFY BEYOND THIS POINT
// ------------------------------------

  var $db_handle;
  var $thispage;
  var $loc;
  var $build_db = false;
  var $from_javascript = false;

  var $failed_post = false;
  var $failures = array();
  var $values = array();

  /**
   * The constructor tries to figure out for
   * which page EasyPageComments is running.
   */
  function __construct() {
    $this->thispage =& $_SERVER["PHP_SELF"];
    $this->loc =& $_SERVER["SCRIPT_URI"]; }

  /**
   * This function ensures the right database
   * is being used for comment read/writing.
   */
  function verify_db($db_name="comments") {
    $db_location = $this->db_dir . "/" . $db_name . ".db";
    $build_db = (!file_exists($db_location));
    $this->db_handle = "sqlite:" . $db_location;
    if($build_db) {
      $dbh = new PDO($this->db_handle);
      $create = "CREATE TABLE comments(id INTEGER PRIMARY KEY AUTOINCREMENT, page TEXT, name TEXT, email TEXT, timestamp INTEGER, body TEXT, replyto INTEGER)";
      $stmt = $dbh->prepare($create);
      $stmt->execute(); }}

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
    if (!$this->isset_for($_POST, array("name", "email", "body", "security", "reply"))) {
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
    $timestamp = date("l, F j") . "<sup>" . date("S") . "</sup>" . date(" Y - g:i a (") . "GMT" . substr(date("P"),0,1) . substr(date("P"),2,1) . ")";
    $body = $this->make_safe($_POST["body"]);
    $answer = trim($_POST["security"]);
    $replyto = intval(str_replace("EasyPageComment","",$this->make_safe($_POST["reply"])));

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
        $insert = 'INSERT INTO comments (page, name, email, timestamp, body, replyto) VALUES ("' . $this->thispage . '", "' . $name . '", "' . $email  . '", "' . $timestamp . '", "' . $body . '", "' . $replyto . '")';
        $result = $dbh->exec($insert);
        $dbh = null;
        $success = ($result==1);

        /**
         * Posting succeeded!
         */
        if($success) {
          $html .= '<input id="EPC-status" name="'.$this->thispage.'" value="SUCCESS">'."\n";

          // if notification is desired, send out a mail
          if($this->notify)
          {
            $message = "A comment was posted on ".$this->loc." by $name ($email):\n";
            $message .= "\n---\n$body\n\n";
            $message .= "click <a href=\"".$this->loc."#EasyPageComments\">here</a> to view this comment online\n";

            $headers = "From: EasyPageComment-Mailer@" . $_SERVER["HTTP_HOST"] . "\r\n" .
                       "Reply-To: $name <$email>\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            mail($this->to, $this->subject, $message, $headers);
          }
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
      print $this->createCommentForm($_GET["getForm"], true); }}

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
    foreach($dbh->query("SELECT * FROM COMMENTS WHERE page LIKE '".$this->thispage."' ORDER BY replyto") as $data)
    {
      $id = $data['id'];

      $html = "\t<div class=\"EPC-entry";
      if($data['name']==$this->admin_alias) { $html .= " EPC-owner-comment"; }
      $html .= "\" id=\"EasyPageComment$id-" . $data['replyto'] . "\">\n";
      $html .= "\t\t<a name=\"$pagename-comment-$id\"></a>\n";
      $html .= "\t\t<div class=\"EPC-entry-name\">" . $data['name'] . "</div>\n";
      $html .= "\t\t<div class=\"EPC-entry-time\"><a href=\"#$pagename-comment-$id\">" . $data['timestamp'] . "</a></div>\n";
      $html .= "\t\t<div class=\"EPC-entry-comment\">" . str_replace("\n","<br/>",$data['body']) . "</div>\n";
      $html .= "\t\t<div class=\"EPC-entry-reply\"><a href=\"#EPC-form-$pagename\" onclick=\"document.querySelector('#EPC-$pagename input[name=reply]').value='EasyPageComment$id'; document.querySelector('.EPC-form-name input').focus()\">reply</a></div>\n";
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
          $e2["children"][] = $e; }
        $entrylist[$i]=null; }}

    // form HTML for threaded topology
    $html = "<div class=\"EPC-list\">\n";
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
        <input type="text" name="name"<?php
          // if posting failed, showing the form on the same page-call should
          // mark errors, and fill in elements with what the user had written.
          if($this->failed_post) {
            echo 'value="' . $this->values["name"]. '"';
            if(isset($this->failures["name"])) {
              echo ' class="EPC-error" title="'.$this->failures["name"].'"';
            }
          }?>></input></div>
      <div class="EPC-form-email">
        <label>Your email:</label>
        <input type="text" name="email"<?php
          // if posting failed, showing the form on the same page-call should
          // mark errors, and fill in elements with what the user had written.
          if($this->failed_post) {
            echo 'value="' . $this->values["email"]. '"';
            if(isset($this->failures["email"])) {
              echo ' class="EPC-error" title="'.$this->failures["email"].'"';
            }
          }?>></input></div>
      <div class="EPC-form-comment">
        <label>Your comment or question:</label>
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
      </div>
      <div class="EPC-form-buttons">
        <input class="EPC-form-clear" type="reset" name="clear" value="clear fields" onclick="document.querySelector('#EPC-<?php echo $page; ?> input[name=reply]').value='0'"></input>
        <input class="EPC-form-submit" type="<?php
          // If we're running on javascript, the submit button should not
          // actually submit the form, but call a javascript function. As
          // such, turn it into an <input type='button'> instead.
          echo ($asynchronous && $page!==false ? "button" : "submit");
        ?>" name="submit" value="post comment"<?php
          if($asynchronous && $page!==false) { echo ' onclick="EasyPageComments.post(\''.$page.'\')"'; }
        ?>></input>
      </div>
    </form>
    <?php
  }
}

/**
  The preceding code sets up the EasyPageComments class,
  the following code actually makes use of it by building
  an EasyPageComments object, and making it do its thing
  based on whether a page was requested via GET or POST.
**/

$EasyPageComments = new EasyPageComments();
if($_SERVER["REQUEST_METHOD"]=="POST") { $EasyPageComments->processPost(); }
elseif($_SERVER["REQUEST_METHOD"]=="GET") { $EasyPageComments->processGET(); }

/**
  After this point, control is returned to whatever included
  the EasyPageComments.php file.
  If EasyPageComments.php was called directly, this script stops here.
**/
?>