var EasyPageComments = {
  EasyPageCommentLocation: "EasyPageComments.php",

  /**
   * Asynchronously fetch the comments list.
   * The reply is sent to a global function
   * 'showEasyPageComments(data)'.
   */
  createCommentsList: function(pagename) {
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if(this.readyState == this.DONE) {
        if(this.status == 200 && this.responseText != null) {
          showEasyPageComments(this.responseText); }}};
    xhr.open("GET",this.EasyPageCommentLocation + "?getList="+pagename,true);
    xhr.send(null);
  },

  /**
   * Asynchronously fetch the comment form.
   * The reply is sent to a global function
   * 'showEasyPageComments(data)'.
   */
  createCommentForm: function(pagename) {
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if(this.readyState == this.DONE) {
        if(this.status == 200 && this.responseText != null) {
          showEasyPageCommentForm(this.responseText); }}};
    xhr.open("GET",this.EasyPageCommentLocation + "?getForm="+pagename,true);
    xhr.send(null);
  },

  /**
   * Asynchronously post a comment.
   * This calls createCommentsList upon completion.
   */
  post: function(pagename) {
    var reply   = document.querySelector("#EPC-form-reply");
    var name    = document.querySelector(".EPC-form-name input");
    var email   = document.querySelector(".EPC-form-email input");
    var comment = document.querySelector(".EPC-form-comment textarea");
    var answer  = document.querySelector(".EPC-security-answer");

    // get form data
    var data = new FormData();
    data.append("reply", reply.value);
    data.append("name", name.value);
    data.append("email", email.value);
    data.append("body", comment.value);
    data.append("security", answer.value);
    data.append("page", pagename);
    
    // clear form
    reply.value=0;
    name.value="";
    email.value="";
    comment.value="";
    answer.value="";

    // post it
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if(this.readyState == this.DONE) {
        if(this.status == 200 && this.responseText != null) {
          EasyPageComments.createCommentsList(pagename); }}};
    xhr.open("POST",this.EasyPageCommentLocation,true);
    xhr.send(data);
  }
};