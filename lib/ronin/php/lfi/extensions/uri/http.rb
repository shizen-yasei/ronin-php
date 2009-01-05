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

require 'ronin/php/lfi/lfi'
require 'ronin/extensions/uri/http'

module URI
  class HTTP < Generic

    def test_lfi(options={})
      up = ((options[:up]) || 0..Ronin::PHP::LFI::MAX_UP)
      vulns = []

      query_params.each_key do |param|
        lfi = Ronin::PHP::LFI.new(self,param)

        up.each do |n|
          lfi.up = n

          if lfi.vulnerable?(options)
            vulns << lfi
            break
          end
        end
      end

      return vulns
    end

    def lfi(options={})
      test_lfi(options).first
    end

    def has_lfi?(options={})
      !(test_lfi(options).empty?)
    end

  end
end
