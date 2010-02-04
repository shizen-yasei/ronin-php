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

require 'ronin/rpc/shell'

module Ronin
  module RPC
    module PHP
      class Shell < RPC::Shell

        #
        # Requests the current working directory.
        #
        # @return [String]
        #   The current working directory.
        #
        def cwd
          call(:cwd)
        end

        #
        # Changes the current working directory.
        #
        # @param [String] path
        #   The directory to switch to.
        #
        def cd(path)
          call(:cd,path)
        end

        #
        # Requests the environment variables.
        #
        # @return [Hash]
        #   The environment variables.
        #
        def env
          call(:env)
        end

        #
        # Requests the environment variable of the given name.
        #
        # @param [String] name
        #   The name of the environment variable to request.
        #
        # @return [String]
        #   The value of the environment variable.
        #
        def getenv(name)
          call(:getenv,name)
        end

        #
        # Sets the environment variable of the given name.
        #
        # @param [String] name
        #   The name of the environment variable to set.
        #
        # @param [String] value
        #   The value to set the environment variable to.
        #
        def setenv(name,value)
          call(:setenv,name,value)
        end

      end
    end
  end
end
