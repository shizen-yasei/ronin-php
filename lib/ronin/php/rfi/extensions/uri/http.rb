#
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
#

require 'ronin/php/rfi/rfi'
require 'ronin/scanners/scanner'
require 'ronin/extensions/uri/http'

module URI
  class HTTP < Generic

    include Ronin::Scanners::Scanner

    #
    # Defines a scanner method for finding Remote File Inclusion (RFI)
    # in specified URLs.
    #
    # @example Scan the url for RFI vulnerabilities.
    #   url.scan_rfi
    #   # => [#<Ronin::PHP::RFI: ...>, ...]
    #
    # @example Scan the url and return the first RFI detected.
    #   url.first_rfi
    #   # => #<Ronin::PHP::RFI: ...>
    #
    # @example Determine if the url is vulnerable to RFI.
    #   url.has_rfi?
    #   # => true
    #
    scanner(:rfi) do |url,results,options|
      url.query_params.each_key do |param|
        rfi = Ronin::PHP::RFI.new(url,param)

        results.call(rfi) if rfi.vulnerable?(options)
      end
    end

    #
    # @deprecated Use {#rfi_scan} instead.
    #
    def test_rfi(*arguments,&block)
      rf_scan(*arguments,&block)
    end

    #
    # @deprecated Use {#first_rfi} instead.
    #
    def rfi(*arguments,&block)
      first_rfi(*arguments,&block)
    end

  end
end
