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

require 'ronin/payloads/web'

module Ronin
  module Payloads
    module PHP
      class Backdoor < Web

        #
        # Creates a new PHP backdoor payload object.
        #
        # @yield []
        #   The given block will be used to create a new PHP backdoor
        #   payload object.
        #
        # @return [Ronin::Payloads::PHP::Backdoor]
        #   The new PHP backdoor payload object.
        #
        # @example
        #   ronin_php_backdoor do
        #     cache do
        #       self.name = 'another PHP backdoor'
        #       self.description = %{
        #         This is another PHP backdoor payload.
        #       }
        #     end
        #
        #     def build
        #     end
        #
        #     def deploy
        #     end
        #   end
        #
        contextify :ronin_php_backdoor

      end
    end
  end
end
