= Ronin PHP

* http://ronin.rubyforge.org/php/
* http://github.com/postmodern/ronin-php
* irc.freenode.net ##ronin
* Postmodern (postmodern.mod3 at gmail.com)

== DESCRIPTION:

Ronin PHP is a Ruby library for Ronin that provides support for PHP
related security tasks.

Ronin is a Ruby platform designed for information security and data
exploration tasks. Ronin allows for the rapid development and distribution
of code over many of the common Source-Code-Management (SCM) systems.

=== Free

All source code within Ronin is licensed under the GPL-2, therefore no user
will ever have to pay for Ronin or updates to Ronin. Not only is the
source code free, the Ronin project will not sell enterprise grade security
snake-oil solutions, give private training classes or later turn Ronin into
commercial software.

=== Modular

Ronin was not designed as one monolithic framework but instead as a
collection of libraries which can be individually installed. This allows
users to pick and choose what functionality they want in Ronin.

=== Decentralized

Ronin does not have a central repository of exploits and payloads which
all developers contribute to. Instead Ronin has Overlays, repositories of
code that can be hosted on any CVS/SVN/Git/Rsync server. Users can then use
Ronin to quickly install or update Overlays. This allows developers and
users to form their own communities, independent of the main developers
of Ronin.

== FEATURES:

* Provides tests for Location File Inclusion (LFI) and Remote File
  Inclusion (RFI) that are built into the URI::HTTP class.
* Allows for effortless finger-printing of a web-server using LFI.
* Provides a PHP-RPC client and server that are designed to work in hostile
  environments.
* Provides an AJAX PHP-RPC Console.

== REQUIREMENTS:

* cssmin
* jsmin
* hpricot
* {ronin}[http://ronin.rubyforge.org/] >= 0.2.2

== INSTALL:

  $ sudo gem install ronin-php

== SYNOPSIS:

* Start the Ronin console with Ronin PHP preloaded:

  $ ronin-php

== LICENSE:

Ronin PHP - A Ruby library for Ronin that provides support for PHP
related security tasks.

Copyright (c) 2007-2009 Hal Brodigan (postmodern.mod3 at gmail.com)

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
