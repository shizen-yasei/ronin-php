#
#--
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007-2009 Hal Brodigan (postmodern.mod3 at gmail.com)
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#++
#

class String

  #
  # Returns +true+ if the String contains a PHP Warning message, returns
  # +false+ otherwise.
  #
  def php_warning?
    !((self =~ /<b>Warning<\/b>:\s+/).nil?)
  end

  #
  # Returns +true+ if the String contains a PHP Error message, returns
  # +false+ otherwise.
  #
  def php_error?
    !((self =~ /<b>Fatal error<\/b>:\s+/).nil?)
  end

end
