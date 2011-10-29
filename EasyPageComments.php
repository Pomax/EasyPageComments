<?php

  $security_answers = array("10", "ten", "１０");
  
  function correctAnswer($given) {
    global $security_answers;
    foreach($security_answers as $answer) {
      if($answer==$given) return true; }
    return false; }

// ------

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
	
	function isset_for($CONTAINER, $keys) {
	  foreach($keys as $key) {
	    if(!isset($CONTAINER[$key])) return false; }
	  return true; }

// ------

  if (isset_for($_POST, array("name", "email", "body", "security")))
  {
    $name = make_safe($_POST["name"]);
    $email = trim($_POST["email"]);
    $timestamp = date("l, F j") . "<sup>" . date("S") . "</sup>" . date(" Y - g:i a (") . "GMT" . substr(date("P"),0,1) . substr(date("P"),2,1) . ")";
    $body = make_safe($_POST["body"]);
    $answer = trim($_POST["security"]);
    
    if(correctAnswer($answer))
    {
      if($name!=""&& valid_email($email) && $body !="")
      {
        $success = true;
        $dbh = new PDO('sqlite:sqlite/comments.db');
        $insert = 'INSERT INTO comments (name,email,timestamp,body) VALUES ("' . $name . '", "' . $email  . '", "' . $timestamp . '", "' . $body . '")';
        $result = $dbh->exec($insert);
        $dbh = null;
        $success = ($result==1);

        if($success) {
          echo "<h1>Thank you for your comment or question</h1>\n";
          echo "<p>Thank you, $name, for your comment or question. I mail email you at $email if your question or comment
          requires a personal or private response. Otherwise, any counter-comments or answers will be posted on the page itself.</p>\n";
          echo "<p>Click <a href='/bezierinfo'>here</a> to return to the primer, or <a href='/bezierinfo#commentsection'>here</a> to return to the comments section.</p>";
          echo '<p style="font-style: italic">-- Mike "Pomax" Kamermans</p>' . "\n";
        
          // tell me about it.
          $to = "pomax@nihongoresources.com";
          $subject = "[pjs.nr.com] Bezierinfo comment posted";
          $message = "A comment was posted on http://processingjs.nihongoresources.com/bezierinfo#commentsection";
          mail($to, $subject, $message); }
        else {
          echo "<h1>Oops...</h1>\n";
          echo "<p>Something went wrong while trying to save your comment or question... I should have received an email about this, so
          I'll try to get this fixed ASAP!</p>"; }
      }
      else {
        echo "<h1>Oops...</h1>\n";
        echo "<p>Something wasn't quite right with your post...</p>\n";
        echo "<ol>\n";
        if($name=="") { echo "<li>You left your name blank.</li>\n"; }
        if(!valid_email($email)) { echo "<li>You filled in an email address, but it wasn't a valid address.</li>\n"; }
        if($body=="") { echo "<li>You forgot the actual comment or question.</li>\n"; }
        echo "</ol>\n";
        echo "<p>Hit back and fix these problems if you wish to retry posting your comment or question.</p>"; 
      }
    }
    
    else {
      echo "<h1>Oops...</h1>\n";
      echo "<p>Apparently, you got the security question wrong. Hit back and fill in the right answer if you wish to post your comment or question (hint: just count the number of entries for secion 4 in the table of content at the top of the page!).</p>";
    }
  }
  
  elseif (isset_for($_GET, array("getList","getForm")))
  {
    if(isset($_GET["getList"]) 
    {
      print createCommentsList();
    }
    elseif(isset($_GET["getForm"]) 
    {
      print createCommentsForm();
    }
  }

  // fetch all comments as HTML
  function createCommentsList()
  {
    $html = "<div class=\"EasyPageComments-list\">\n";
    $dbh = new PDO('sqlite:sqlite/comments.db');
    $row = 1;
    foreach($dbh->query('SELECT * FROM COMMENTS') as $data)
    {
      $html .= "\t<div class=\"EasyPageComments-entry\" id=\"EasyPageComment$row\">\n";
      $html .= "\t\t<a name=\"EasyPageComment$row\">\n";
      $html .= "\t\t<div class=\"EasyPageComments-entry-name\">" . $data['name'] . "</div>\n";
      $html .= "\t\t<div class=\"EasyPageComments-entry-time\"><a href=\"#EasyPageComment$row\">" . $data['timestamp'] . "</a></div>\n";
      $html .= "\t\t<div class=\"EasyPageComments-entry-comment\">" . str_replace("\n","<br/>",$data['body']) . "</div>\n";
      $html .= "\t</div> <!-- EasyPageComments entry -->\n";
      $row++;
    }
    $dbh = null;
    $html .= "</div> <!-- EasyPageComments list -->\n";
    return $htm;
  }


  function createCommentForm()
  {
    $form = ?>
<table style="margin-right: auto;"><tr><td>
	<form action="postcomment.php" method="post">
		<div>
		Your name: <input type="text" name="name" style="display:block;"></input>
		Your email	<span style="font-size:80%">(may be used by me to contact you personally if your comment or question warrants it)</span>: <input type="text" name="email" style="display:block;"></input>
		Your comment or question:
		<textarea name="body" rows="12" cols="65" style="display:block;"></textarea>
		How many subsctions does section 4 have? <span style="font-size: 80%">(security question)</span>: <input type="text" name="security" style="width: 4em;"></input>
		<input type="submit" name="submit" value="post comment"></input>
		</div>
	</form>
</td></tr></table><?php;
    return $form;
  }
?>