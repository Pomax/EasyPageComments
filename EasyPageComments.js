var EasyPageComments = {
  EasyPageCommentLocation: "EasyPageComments.php",

  createCommentsList: function(pagename) {
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if(this.readyState == this.DONE) {
        if(this.status == 200 && this.responseText != null) {
          showEasyPageComments(this.responseText); }}};
    xhr.open("GET",this.EasyPageCommentLocation + "?getList="+pagename,true);
    xhr.send(null);
  },

  createCommentForm: function(pagename) {
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if(this.readyState == this.DONE) {
        if(this.status == 200 && this.responseText != null) {
          showEasyPageCommentForm(this.responseText); }}};
    xhr.open("GET",this.EasyPageCommentLocation + "?getForm="+pagename,true);
    xhr.send(null);
  }
};