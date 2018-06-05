# EasyPageComments

(c) 2011 Mike "Pomax" Kamermans

## Unmaintained

This code was written in 2011 and has not been looked at
in years. If you would like to see any work done, the
only realistic path towards getting changes landed will
be to write the fixes/modifications yourself, and filing
a PR, as I will not be writing any code against this
package myself.

## Synopsis

This projects aims to make the concept of quickly adding a
comments section to pages trivial, by offering a PHP script
(EasyPageCommentsphp) that takes care of the message
administration (using PDO-SQLite for storage) as well as
the content generation. An explanation can be found on:

  http://pomax.nihongoresources.com/pages/EasyPageComments

## Features

- Drop-in install
- Separate comments listing and comment form injection
- All elements can be individually styled using CSS
- Threaded commenting with nested replies
- Allows multiple comment sections on a single page
- Email post and reply notification
- Posting as owner is password protected
- RSS feed link per comment section
- Simplified posting for users you say are logged in
  (no need for them to fill in their name and email
  address or security questions)
- Works in either simple or rich mode, depending on
  whether you need to support browsers that don't support
  JavaScript.
- Backed SQLite (file based) databases, which makes it
  really easy to migrate and/or administrate
- Hash aware. Regardless of how late you generate comments,
  jumping to hash still works.
- Error highlighting when trying to post with missing
  or illegal form fields.


## License

public domain, except in jurisditions where public
domain is not recognized. In those jurisdictions,
the code license is to be considered MIT.

## Disclaimer

Use this code at your own risk. If it does not do
what you expected it to do, the person responsible
for that is you, for not analysing the code to make
sure it would do what you would have expected it to
do. I take no responsibility for how you use this
code, or what expectations you have of it.
