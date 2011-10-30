<?php

  // this is used to mark your own posts differently from everyone else's
  $admin_alias = "Pomax";

  // what should be the security question for this page?
  $security_question = "If I write a one, and then a zero, which number did I write?";

  // what are valid security answers for this page?
  $security_answers = array("10", "ten", "１０");

  // should you be notified by email when someone posts a comment?
  $notify = true;

  // what's the email address to send those notifications to?
  $to = "pomax@nihongoresources.com";

  // what's the subject you'd like?
  $subject = "[EasyPageComments] page comment posted";


// ------

  $loc =& $_SERVER["SCRIPT_URI"];

	function make_safe($string) {
		$string = str_replace("<p>","",$string);
		$string = str_replace("</p>","\n\n",$string);
		$string = str_replace("<br>","\n",$string);
		$string = str_replace("<br/>","\n",$string);
		$string = str_replace(";","&#59;",$string);
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
    global $security_answers;
    foreach($security_answers as $answer) {
      if($answer==$given) return true; }
    return false; }

// ------

  // POST functionality
  if (isset_for($_POST, array("name", "email", "body", "security", "reply")))
  {
    $name = make_safe($_POST["name"]);
    $email = trim($_POST["email"]);
    $timestamp = date("l, F j") . "<sup>" . date("S") . "</sup>" . date(" Y - g:i a (") . "GMT" . substr(date("P"),0,1) . substr(date("P"),2,1) . ")";
    $body = make_safe($_POST["body"]);
    $answer = trim($_POST["security"]);
    $replyto = intval(str_replace("EasyPageComment","",make_safe($_POST["reply"])));

    $html = "<div class=\"EasyPageComments-response\">\n";

    if(correctAnswer($answer))
    {
      if($name!=""&& valid_email($email) && $body !="")
      {
        $success = true;
        $dbh = new PDO('sqlite:sqlite/comments.db');
        $insert = 'INSERT INTO comments (name,email,timestamp,body,replyto) VALUES ("' . $name . '", "' . $email  . '", "' . $timestamp . '", "' . $body . '", "' . $replyto . '")';
        $result = $dbh->exec($insert);
        $dbh = null;
        $success = ($result==1);

        /**
         * Posting succeeded
         */
        if($success) {
          $html .= "<div id=\"EasyPageCommentStatus\">succeeded</div>\n";
          $html .= "<div class=\"EasyPageComments-response-title\">Thank you for your comment or question</div>\n";
          $html .= "<div class=\"EasyPageComments-response-text\">\n";
          // you can modify this as much as you like, really
          $html .= "<p>Thank you for your comment or question, $name. You may be mailed $email directly if your";
          $html .= " question or comment requires a personal or private response. Otherwise, any counter-comments";
          $html .= " or answers will be posted on the page itself.</p>\n";
          $html .= "</div> <!-- EasyPageComments-response-text -->\n";

          // if notification is desired, send out a mail
          if($notify)
          {
            $message = "A comment was posted on $loc by $name ($email):\n";
            $message .= "\n---\n$body\n\n";
            $message .= "click <a href=\"$loc#EasyPageComments\">here</a> to view this comment online\n";

            $headers = "From: EasyPageComment-Mailer@" . $_SERVER["HTTP_HOST"] . "\r\n" .
                       "Reply-To: '$name' <$email>\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            mail($to, $subject, $message, $headers);
          }
        }

        /**
         * Posting failed, despite all values conforming to acceptable formats
         */
        else
        {
          $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
          $html .= "<div class=\"EasyPageComments-response-title\">Oops...</div>\n";
          $html .= "<div class=\"EasyPageComments-response-text\">\n";
          // you can modify this as much as you like, really
          $html .= "<p>Something went wrong while trying to save your comment or question... I";
          $html .= " should have received an email about this, so I'll try to get this fixed ASAP!</p>";
          $html .= "</div> <!-- EasyPageComments-response-text -->\n";

          $message = "An error occurred while trying to save an EasyPageComments comment for $loc\n";
          $message = "Message data:\n";
          $message = "NAME   : $name\n";
          $message = "EMAIL  : $email\n";
          $message = "COMMENT: $body\n";

          $headers = "From: EasyPageComment-Mailer@" . $_SERVER["HTTP_HOST"] . "\r\n" .
                     "Reply-To: '$name' <$email>\r\n" .
                     "X-Mailer: PHP/" . phpversion();

          // notification is not optional here. You will receive error mails.
          mail($to, "EasyPageComments error", $message, $headers);
        }
      }

      /**
       * Posting could not be performed - data was missing, or of the wrong format
       */
      else {
        $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
        $html .= "<div class=\"EasyPageComments-response-title\">Oops...</div>\n";
        $html .= "<div class=\"EasyPageComments-response-text\">\n";
        // you can modify this as much as you like, but try to keep that list in there.
        $html .= "<p>Something wasn't quite right with your post...</p>\n";
        $html .= "<ol>\n";
        if($name=="") { $html .= "<li>You left your name blank.</li>\n"; }
        if(!valid_email($email)) { $html .= "<li>You filled in an email address, but it wasn't a valid address.</li>\n"; }
        if($body=="") { $html .= "<li>You forgot the actual comment or question.</li>\n"; }
        $html .= "</ol>\n";
        $html .= "</div> <!-- EasyPageComments-response-text -->\n";
      }
    }

    /**
     * Posting could not be performed - the security question was answered incorrectly
     */
    else {
      $html .= "<div id=\"EasyPageCommentStatus\">failed</div>\n";
      $html .= "<div class=\"EasyPageComments-response-title\">Oops...</div>\n";
      $html .= "<div class=\"EasyPageComments-response-text\">\n";
      // you can modify this as much as you like, really.
      $html .= "<p>Apparently, you got the security question wrong. Hit back and fill in";
      $html .= " the right answer if you wish to post your comment or question ";
      $html .= "(hint: the question already implies the answer).</p>";
      $html .= "</div> <!-- EasyPageComments-response-text -->\n";
    }
    $html .= "</div> <!-- EasyPageComments-response -->\n";

    // finally, print the aggregated HTML code
    print $html;
  }

// ------

  // GET functionality
  elseif (isset_for($_GET, array("getList","getForm")))
  {
    if(isset($_GET["getList"]))
    {
      print createCommentsList();
    }
    elseif(isset($_GET["getForm"]))
    {
      print createCommentsForm();
    }
  }

// ------

  // fetch all comments as HTML
  function createCommentsList()
  {
    global $admin_alias;

    $entrylist = array();

    $dbh = new PDO('sqlite:sqlite/comments.db');
    foreach($dbh->query('SELECT * FROM COMMENTS ORDER BY replyto') as $data)
    {
      $row = $data['id'];

      $html = "<div class=\"EasyPageComments-list\">\n";
      $html .= "\t<div class=\"EasyPageComments-entry";
      if($data['name']==$admin_alias) { $html .= " EasyPageComments-owner-comment"; }
      if($data['replyto']!=0) { $html .= " EasyPageComments-entry-nested"; }
      $html .= "\" id=\"EasyPageComment$row-" . $data['replyto'] . "\">\n";
      $html .= "\t\t<a name=\"EasyPageComment$row\"></a>\n";
      $html .= "\t\t<div class=\"EasyPageComments-entry-name\">" . $data['name'] . "</div>\n";
      $html .= "\t\t<div class=\"EasyPageComments-entry-time\"><a href=\"#EasyPageComment$row\">" . $data['timestamp'] . "</a></div>\n";
      $html .= "\t\t<div class=\"EasyPageComments-entry-comment\">" . str_replace("\n","<br/>",$data['body']) . "</div>\n";
      $html .= "\t\t<div class=\"EasyPageComments-entry-reply\"><a href=\"#EasyPageComment-form\" onclick=\"document.getElementById('EasyPageComments-form-reply').value='EasyPageComment$row'\">reply</a></div>\n";
      $html .= "\t</div> <!-- EasyPageComments entry -->\n";
      $html .= "</div> <!-- EasyPageComments list -->\n";

      $entrylist[] = array("id"=>$row, "nest"=>$data["replyto"], "html"=>$html);
    }
    $dbh = null;

    // reorder the elements so that they're threaded
    for($i=1; $i<count($entrylist); $i++) {
      $element = $entrylist[$i];
      if($element["nest"]!=0) {
        // find out where we should put this thing
        for($j=0; $j<$i; $j++) {
          $e = $entrylist[$j];
          if($e["id"]==$element["nest"]) {
            // found you. we insert after this element.
            array_splice($entrylist, $i, 1);
            $tmp = array();
            $tmp = array_slice($entrylist, 0, $j+1);
            $tmp[] = $element;
            $tmp = array_merge($tmp, array_slice($entrylist, $j+1));
            $entrylist = $tmp;
            break; }}}}

    $html = "";
    foreach($entrylist as $entry) {
      foreach($entry as $key=>$val) {
        if($key=="html") { $html .= $val; }}}

    print $html;
  }


  function createCommentForm(){
    global $security_question;
    ?>
    <a name="EasyPageComment-form"></a>
    <form class="EasyPageComments-form" action="EasyPageComments.php" method="POST">
      <input id="EasyPageComments-form-reply" type="hidden" name="reply" value="EasyPageComment0">
      <div class="EasyPageComments-form-name"><label>Your name:</label><input type="text" name="name"></input></div>
      <div class="EasyPageComments-form-email"><label>Your email:</label><input type="text" name="email"></input></div>
      <div class="EasyPageComments-form-comment">
        <label>Your comment or question:</label>
        <textarea name="body" rows="12" cols="65" style="display:block;"></textarea>
      </div>
      <div class="EasyPageComments-form-security">
        <div class="EasyPageComments-form-security-question"><?php echo $security_question; ?>
        <input class="EasyPageComments-form-security-answer" type="text" name="security" style="width: 4em;"></input>
      </div>
      <input class="EasyPageComments-form-clear" type="reset" name="clear" value="clear fields"></input>
      <input class="EasyPageComments-form-submit" type="submit" name="submit" value="post comment"></input>
      </div>
    </form>
    <?php }


?>