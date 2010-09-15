#
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007-2010 Hal Brodigan (postmodern.mod3 at gmail.com)
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

module URI
  class HTTP < Generic

    #
    # @see Ronin::PHP::LFI.scan
    #
    def rfi_scan(options={})
      Ronin::PHP::RFI.scan(self,options)
    end

    #
    # Attempts to find the first RFI vulnerability in the URL.
    #
    # @param [Hash] options
    #   Additional options.
    #
    # @return [Ronin::PHP::RFI, nil]
    #   The first RFI vulnerability discovered.
    #
    def first_rfi(options={})
      rfi_scan(options).first
    end

    #
    # Determines if the URL is vulnerable to Remote File Inclusion (RFI).
    #
    # @param [Hash] options
    #   Additional options.
    #
    # @return [Boolean]
    #   Specifies whether the URL is vulnerable to RFI.
    #
    def has_rfi?(options={})
      !(first_rfi(options).nil?)
    end

    #
    # @deprecated Use {#rfi_scan} instead.
    #
    def test_rfi(*arguments,&block)
      rfi_scan(*arguments,&block)
    end

    #
    # @deprecated Use {#first_rfi} instead.
    #
    def rfi(*arguments,&block)
      first_rfi(*arguments,&block)
    end

  end
end
