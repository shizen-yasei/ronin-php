### 0.1.4 / 2009-09-24

* Require ronin >= 0.3.0.
* Require ronin-web >= 0.2.0.
* Require rspec >= 1.2.8.
* Require yard >= 0.2.3.5.
* Added Ronin::PHP::RFI.test_script.
* Added Ronin::PHP::RFI.test_script=.
* Added Ronin::PHP::RFI.rpc_script.
* Added Ronin::PHP::RFI.rpc_script=.
* Moved to YARD based documentation.
* Updated the project summary and 3-point description for Ronin PHP.
* Fixed the URL for Ronin::PHP::RFI::TEST_SCRIPT.
* Fixed the URL for Ronin::PHP::RFI::RPC_SCRIPT.
* Fixed a bug with how the exec_output() function executes commands under
  PHP5.

### 0.1.3 / 2009-07-25

* Fixed a show-stopping typo in ronin/php/lfi/extensions/uri/http.rb
  that was preventing ronin-php from being loaded.

### 0.1.2 / 2009-07-02

* Use Hoe >= 2.0.0.
* Require ronin >= 0.2.4.
* Require ronin-web >= 0.1.3.
* Use Ronin::Scanners::Scanner to define scanners for
  Ronin::PHP::LFI and Ronin::PHP::RFI objects on
  URI::HTTP urls.

### 0.1.1 / 2009-03-28

* Require hpricot.
* Require ronin >= 0.2.2.
* Require ronin-web >= 0.1.2.
* Use http://ronin.rubyforge.org/static/ for hosting static content.
* Place all static content under the static/ronin/ directory.
* Simplified RFI#vulnerable?.
  * Don't use the Chars library to generate the RFI challenge string.
  * Use Digest::MD5.hexdigest directly for encoding the RFI challenge
    string.

### 0.1.0 / 2009-01-08

* Require Ronin >= 0.1.3.
* Removed references to Ronin::Vulnerable.
* Use Ronin::Config.load to load <tt>~/.ronin/config/php.rb</tt>.
* Added missing files to the Manifest.
* Added more documentation.
* Added more specs.

### 0.0.9 / 2008-07-30

* Initial release.
* Provides tests for Location File Inclusion (LFI) and Remote File
  Inclusion (RFI) that are built into the URI::HTTP class.
* Allows for effortless finger-printing of a web-server using LFI.
* Provides a PHP-RPC client and server that are designed to work in hostile
  environments.
* Provides an AJAX PHP-RPC Console.

