var EasyPageComments = {

  // Change this value if you don't have the
  // EasyPageComments.php file in the same
  // directory as your web page.
  EasyPageCommentLocation: "EasyPageComments.php",

  /**
   * Using anchor links with JavaScript will fail
   * to jump to the anchor because often the comments
   * will not be generated until after DOMReady.
   * the first time createCommentsList is called,
   * and the URL bar has an anchor, we use this
   * variable to determine whether to 'follow' the
   * anchor indication
   */
  followAnchor: window.location.hash,

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
          showEasyPageComments(this.responseText);
          // see if we need to jump to an anchor
          if(EasyPageComments.followAnchor && this.responseText.indexOf(EasyPageComments.followAnchor)>-1) {
            window.location = EasyPageComments.followAnchor;
            EasyPageComments.followAnchor = false; }}}};
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
   * Visually inform the user that there are errors with their post
   */
  notifyOfError: function(pagename) {
    var errmsg = "There are some problems with your comment that you need to fix before posting.";
    document.querySelector("#EPC-"+pagename+" .EPC-error-message").innerHTML = errmsg;
  },

  /**
   * Visually inform the user that an element has issues
   */
  markError: function(element, message) {
    if(element==null) return;
    var css = element.getAttribute("class");
    if(css==null) { css=""; }
    css += " EPC-error";
    element.setAttribute("class", css);
    element.setAttribute("title", message);
  },

  /**
   * remove the visual error
   */
  clearError: function(element) {
    if(element==null) return;
    var css = element.getAttribute("class");
    if(css==null) return;
    css = css.replace(" EPC-error","");
    element.setAttribute("class", css);
    element.setAttribute("title", "");
  },

  /**
   * Append an element's "value" to a FormData object
   */
  appendToFormData: function(data, name, selector) {
    var element = document.querySelector(selector);
    this.clearError(element);
    data.append(name, element.value);
  },

  /**
   * Asynchronously post a comment.
   * This calls createCommentsList upon completion.
   */
  post: function(pagename, trusted) {
    // set up form data
    var data = new FormData();
    this.appendToFormData(data, "reply",   "#EPC-"+pagename+" .EPC-form-reply");
    this.appendToFormData(data, "body",    "#EPC-"+pagename+" .EPC-form-comment textarea");
    this.appendToFormData(data, "name",    "#EPC-"+pagename+" .EPC-form-name input");
    this.appendToFormData(data, "email",   "#EPC-"+pagename+" .EPC-form-email input")
    if(!trusted) { this.appendToFormData(data, "security","#EPC-"+pagename+" .EPC-security-answer"); }
    data.append("page", pagename);

    // post it
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if(this.readyState == this.DONE) {
        if(this.status == 200 && this.responseText != null) {
          // get the POST response
          var response = document.createElement("div");
          response.innerHTML = this.responseText;
          response.style.visibility = "hidden";
          response.id="EPC-response-"+pagename;
          document.body.appendChild(response);
          var status = document.querySelector("#EPC-status"),
              thread = status.name,
              result = status.value;

          // this is essentially terminal.
          if(result=="ERROR") {
            alert("an error occured while posting your message. The page administrator has been notified.");
            EasyPageComments.createCommentForm(pagename); }

          // visually inform the user about what went wrong
          else if(result=="FAILED") {
            EasyPageComments.notifyOfError(pagename);
            // mark each error element
            var e, end, errors = document.querySelectorAll("#"+response.id+" input");
            for(e=1, end=errors.length; e<end; e++) {
              var thing   = errors[e].name;
              var message = errors[e].value;
              var element;
              if(thing=="body") { element = document.querySelector("#EPC-"+pagename+" textarea"); }
              else { element = document.querySelector("#EPC-"+pagename+" input[name="+thing+"]"); }
              EasyPageComments.markError(element, message); }}

          // all went well. clear the form and update the comments.
          else if(result=="SUCCESS") {
            EasyPageComments.createCommentsList(pagename);
            EasyPageComments.createCommentForm(pagename); }

          // and make sure to remove the response from the body again!
          document.body.removeChild(response);
        }
      }
    };
    xhr.open("POST",this.EasyPageCommentLocation+"?caller=JavaScript",true);
    xhr.send(data);
  },

  // state variable for the monitorAlias function
  ownerAlias: false,

  /**
   * Username input monitor. If the username matches
   * the owner nickname, the email field becomes a
   * password field instead (this is also verified
   * at the backend, so simply hacking the JS to compare
   * against a different nickname won't do anything.
   * This function is purely cosmetic)
   */
  monitorAlias: function(e, comment_section, element, owner) {
    var text = element.value;
    var label, input;
    if(!this.ownerAlias && text==owner) {
      label = document.querySelector('#EPC-'+comment_section+' .EPC-form-email label');
      input = document.querySelector('#EPC-'+comment_section+' .EPC-form-email input');
      input.type = "password";
      label.innerHTML = "Password:";
      // hide security question, it's irrelevant for the owner
      document.querySelector('#EPC-'+comment_section+' .EPC-security').style.visibility = "hidden";
      this.ownerAlias = true;
    }
    else if(this.ownerAlias && text!=owner) {
      label = document.querySelector('#EPC-'+comment_section+' .EPC-form-email label');
      input = document.querySelector('#EPC-'+comment_section+' .EPC-form-email input');
      input.type="text";
      input.value="";
      label.innerHTML = "Your email:";
      document.querySelector('#EPC-'+comment_section+' .EPC-security').style.visibility = "visible";
      this.ownerAlias = false;
    }
  }
};