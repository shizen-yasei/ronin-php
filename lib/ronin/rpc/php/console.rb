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

require 'ronin/rpc/console'

module Ronin
  module RPC
    module PHP
      class Console < RPC::Console

        def fingerprint
          profile = {
            :uname => php_uname,
            :php_server_api => php_sapi_name,
            :php_version => phpversion,
            :uid => posix_getuid,
            :gid => posix_getgid,
            :cwd => getcwd,
            :disk_free_space => disk_free_space('/'),
            :disk_total_space => disk_total_space('/')
          }

          case profile[:php_server_api]
          when 'apache'
            profile[:apache_version] = apache_get_version
          end

          return profile
        end

      end
    end
  end
end
