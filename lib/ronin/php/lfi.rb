#
#--
# Ronin PHP - A Ruby library for Ronin that provides support for PHP
# related security tasks.
#
# Copyright (c) 2007-2008 Hal Brodigan (postmodern.mod3 at gmail.com)
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

require 'ronin/extensions/uri'
require 'ronin/network/http'

module Ronin
  module PHP
    module LFI
      def LFI.get(url,param,file)
        url = URI(url.to_s)
        url.query_params[param] = file

        return Net.http_get_body(:url => url)
      end

      def LFI.test_url(url,options={},&block)
        url = URI(url.to_s)
        file = options[:file]

        return uri.test_query_params(file,options) do |lfi_url|
          block.call(Net.http_get(options.merge(:url => lfi_url)))
        end
      end
    end
  end
end
