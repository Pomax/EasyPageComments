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
  var $to = "pomax@nihongoresources.com";

  // what's the subject you'd like?
  var $subject = "[EasyPageComments] page comment posted";

  // where can the sqlite database be found
  var $db_location = "sqlite/comments.db";

// ------------------------------------
//    DO NOT MODIFY BEYOND THIS POINT
// ------------------------------------

  var $db_handle;
  var $thispage;
  var $loc;
  var $build_db = false;

  function __construct() {
    $this->db_handle = "sqlite:".$this->db_location;
    $this->thispage =& $_SERVER["PHP_SELF"];
    $this->loc =& $_SERVER["SCRIPT_URI"];
    $this->build_db = (!file_exists($this->db_location)); }

  function verify_db() {
    if($this->build_db) {
      $dbh = new PDO($this->db_handle);
      $create = "CREATE TABLE comments(id INTEGER PRIMARY KEY AUTOINCREMENT, page TEXT, name TEXT, email TEXT, timestamp INTEGER, body TEXT, replyto INTEGER)";
      $stmt = $dbh->prepare($create);
      $stmt->execute(); }}

  // ------

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

	// the only correct regular expression to use for email addresses
	function valid_email($email) {
		$regexp = "/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i";
		return preg_match($regexp, $email) == 1; }

  // test whether a container has all required keys
	function isset_for($CONTAINER, $keys) {
	  foreach($keys as $key) {
	    if(!isset($CONTAINER[$key])) return false; }
	  return true; }

  // test whether the security question was correctly answered
  function correctAnswer($given) {
    foreach($this->security_answers as $answer) {
      if($answer==$given) return true; }
    return false; }

// ------


  // POST functionality
  function processPOST()
  {
    if (!$this->isset_for($_POST, array("name", "email", "body", "security", "reply"))) {
      $html  = "<div class=\"EPC-response\">\n";
      $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
      $html .= "<div class=\"EPC-response-title\">Missing fields</div>\n";
      $html .= "<p>Could not process form: required element values are missing.</p>";
      $html .= "</div> <!-- EPC-response-text -->\n";
      print $html;
      return;
    }

    $name = $this->make_safe($_POST["name"]);
    $email = trim($_POST["email"]);
    $timestamp = date("l, F j") . "<sup>" . date("S") . "</sup>" . date(" Y - g:i a (") . "GMT" . substr(date("P"),0,1) . substr(date("P"),2,1) . ")";
    $body = $this->make_safe($_POST["body"]);
    $answer = trim($_POST["security"]);
    $replyto = intval(str_replace("EasyPageComment","",$this->make_safe($_POST["reply"])));

    // default page override?
    if($_POST["page"]) { $this->thispage = $this->make_safe($_POST["page"]); }

    $html = "<div class=\"EPC-response\">\n";

    if($this->correctAnswer($answer))
    {
      if($name!=""&& $this->valid_email($email) && $body !="")
      {
        $success = true;
        $dbh = new PDO($this->db_handle);

         // insert the comment
        $insert = 'INSERT INTO comments (page, name,email,timestamp,body,replyto) VALUES ("' . $this->thispage . '", "' . $name . '", "' . $email  . '", "' . $timestamp . '", "' . $body . '", "' . $replyto . '")';
        $result = $dbh->exec($insert);
        $dbh = null;
        $success = ($result==1);

        /**
         * Posting succeeded!
         */
        if($success) {
          $html .= "<div id=\"EasyPageCommentStatus\">succeeded</div>\n";
          $html .= "<div class=\"EPC-response-title\">Thank you for your comment or question</div>\n";
          $html .= "<div class=\"EPC-response-text\">\n";
          // you can modify this as much as you like, really
          $html .= "<p>Thank you for your comment or question, $name. You may be mailed $email directly if your";
          $html .= " question or comment requires a personal or private response. Otherwise, any counter-comments";
          $html .= " or answers will be posted on the page itself.</p>\n";
          $html .= "</div> <!-- EPC-response-text -->\n";

          // if notification is desired, send out a mail
          if($this->notify)
          {
            $message = "A comment was posted on ".$this->loc." by $name ($email):\n";
            $message .= "\n---\n$body\n\n";
            $message .= "click <a href=\"".$this->loc."#EasyPageComments\">here</a> to view this comment online\n";

            $headers = "From: EasyPageComment-Mailer@" . $_SERVER["HTTP_HOST"] . "\r\n" .
                       "Reply-To: '$name' <$email>\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            mail($this->to, $this->subject, $message, $headers);
          }
        }

        /**
         * Posting failed, despite all values conforming to acceptable formats
         */
        else
        {
          $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
          $html .= "<div class=\"EPC-response-title\">Oops...</div>\n";
          $html .= "<div class=\"EPC-response-text\">\n";
          // you can modify this as much as you like, really
          $html .= "<p>Something went wrong while trying to save your comment or question... I";
          $html .= " should have received an email about this, so I'll try to get this fixed ASAP!</p>";
          $html .= "</div> <!-- EPC-response-text -->\n";

          $message = "An error occurred while trying to save an EasyPageComments comment for ".$this->loc."\n";
          $message = "Message data:\n";
          $message = "NAME   : $name\n";
          $message = "EMAIL  : $email\n";
          $message = "COMMENT: $body\n";

          $headers = "From: EasyPageComment-Mailer@" . $_SERVER["HTTP_HOST"] . "\r\n" .
                     "Reply-To: '$name' <$email>\r\n" .
                     "X-Mailer: PHP/" . phpversion();

          // notification is not optional here. You will receive error mails.
          mail($this->to, "EasyPageComments error", $message, $headers);
        }
      }

      /**
       * Posting could not be performed - data was missing, or of the wrong format
       */
      else {
        $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
        $html .= "<div class=\"EPC-response-title\">Oops...</div>\n";
        $html .= "<div class=\"EPC-response-text\">\n";
        // you can modify this as much as you like, but try to keep that list in there.
        $html .= "<p>Something wasn't quite right with your post...</p>\n";
        $html .= "<ol>\n";
        if($name=="") { $html .= "<li>You left your name blank.</li>\n"; }
        if(!valid_email($email)) { $html .= "<li>You filled in an email address, but it wasn't a valid address.</li>\n"; }
        if($body=="") { $html .= "<li>You forgot the actual comment or question.</li>\n"; }
        $html .= "</ol>\n";
        $html .= "</div> <!-- EPC-response-text -->\n";
      }
    }

    /**
     * Posting could not be performed - the security question was answered incorrectly
     */
    else {
      $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
      $html .= "<div class=\"EPC-response-title\">Oops...</div>\n";
      $html .= "<div class=\"EPC-response-text\">\n";
      // you can modify this as much as you like, really.
      $html .= "<p>Apparently, you got the security question wrong. Hit back and fill in";
      $html .= " the right answer if you wish to post your comment or question ";
      $html .= "(hint: the question already implies the answer).</p>";
      $html .= "</div> <!-- EPC-response-text -->\n";
    }
    $html .= "</div> <!-- EPC-response -->\n";

    // finally, print the aggregated HTML code
    print $html;
  }

// ------

  // GET functionality
  function processGET() {
    if(isset($_GET["getList"])) {
      print $this->createCommentsList($_GET["getList"]); }
    elseif(isset($_GET["getForm"])) {
      print $this->createCommentForm($_GET["getForm"]); }}

// ------

  // fetch all comments as HTML
  function createCommentsList($pagename=false)
  {
    $entrylist = array();
    if($pagename!==false) { $this->thispage = $pagename; }

    $dbh = new PDO($this->db_handle);
    foreach($dbh->query("SELECT * FROM COMMENTS WHERE page LIKE '".$this->thispage."' ORDER BY replyto") as $data)
    {
      $id = $data['id'];

      $html = "\t<div class=\"EPC-entry";
      if($data['name']==$this->admin_alias) { $html .= " EPC-owner-comment"; }
      $html .= "\" id=\"EasyPageComment$id-" . $data['replyto'] . "\">\n";
      $html .= "\t\t<a name=\"EasyPageComment$id\"></a>\n";
      $html .= "\t\t<div class=\"EPC-entry-name\">" . $data['name'] . "</div>\n";
      $html .= "\t\t<div class=\"EPC-entry-time\"><a href=\"#EasyPageComment$id\">" . $data['timestamp'] . "</a></div>\n";
      $html .= "\t\t<div class=\"EPC-entry-comment\">" . str_replace("\n","<br/>",$data['body']) . "</div>\n";
      $html .= "\t\t<div class=\"EPC-entry-reply\"><a href=\"#EasyPageComment-form\" onclick=\"document.getElementById('EPC-form-reply').value='EasyPageComment$id'; document.querySelector('.EPC-form-name input').focus()\">reply</a></div>\n";
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
   * Recursively walk a "node set" for html stringification
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
   * generate the HTML form for posting a comment
   */
  function createCommentForm($page=false) {
    ?>
    <a name="EasyPageComment-form"></a>
    <form class="EPC-form" action="." method="POST"><?php
    if($page!==false) { ?>
      <input id="EPC-form-page" type="hidden" name="page" value="<?php echo $page; ?>">
<?php } ?>
      <input id="EPC-form-reply" type="hidden" name="reply" value="0">
      <div class="EPC-form-name"><label>Your name:</label><input type="text" name="name"></input></div>
      <div class="EPC-form-email"><label>Your email:</label><input type="text" name="email"></input></div>
      <div class="EPC-form-comment">
        <label>Your comment or question:</label>
        <textarea name="body"></textarea>
      </div>
      <div class="EPC-security">
        <div class="EPC-security-question"><?php echo $this->security_question; ?></div>
        <input class="EPC-security-answer" type="text" name="security"></input>
      </div>
      <div class="EPC-form-buttons">
        <input class="EPC-form-clear" type="reset" name="clear" value="clear fields"></input>
        <input class="EPC-form-submit" type="submit" name="submit" value="post comment"></input>
      </div>
    </form>
    <?php
  }
}

// build Easy Page Comments object
$EasyPageComments = new EasyPageComments();

// verify the database is in the right place
$EasyPageComments->verify_db();

// immediately process POST/GET requests
if($_SERVER["REQUEST_METHOD"]=="POST") { $EasyPageComments->processPost(); }
elseif($_SERVER["REQUEST_METHOD"]=="GET") { $EasyPageComments->processGET(); }

// return control to the inclusion-calling script
?>