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

require 'ronin/php/lfi/lfi'

module URI
  class HTTP < Generic

    #
    # @see Ronin::PHP::LFI.scan
    #
    def lfi_scan(options={})
      Ronin::PHP::LFI.scan(self,options)
    end

    #
    # Attempts to find the first LFI vulnerability of the URL.
    #
    # @param [Hash] options
    #   Additional options.
    #
    # @option options [Range] :up
    #   The number of directories to attempt traversing up.
    #
    # @return [Ronin::PHP::LFI]
    #   The first LFI vulnerability found.
    #
    def first_lfi(options={})
      Ronin::PHP::LFI.scan(self,options).first
    end

    #
    # Determines if the URL is vulnerable to Local File Inclusion (LFI).
    #
    # @param [Hash] options
    #   Additional options.
    #
    # @option options [Range] :up
    #   The number of directories to attempt traversing up.
    #
    # @return [Boolean]
    #   Specifies whether the URL is vulnerable to LFI.
    #
    def has_lfi?(options={})
      !(first_lfi(options).nil?)
    end

    #
    # @deprecated Use {#lfi_scan} instead.
    #
    def test_lfi(*arguments,&block)
      lfi_scan(*arguments,&block)
    end

    #
    # @deprecated Use {#first_lfi} instead.
    #
    def lfi(*arguments,&block)
      first_lfi(*arguments,&block)
    end

  end
end
