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

require 'ronin/php/lfi/target'

module Ronin
  module PHP
    class LFI
      Target.log do |target|
        target.paths['Linux'] = ['/var/log/wtmp']
        target.paths['Solaris'] = ['/var/log/wtmp']

        target.recognizor = /(tty\d+|:\d+)/
      end

      Target.log do |target|
        target.paths['Linux'] = ['/var/log/apache/rewrite.log', '/var/log/apache2/rewrite.log']

        target.recognizor = /init rewrite engine with requested uri/
      end

      Target.log do |target|
        target.paths['Linux'] = ['/etc/syslog.conf']
        target.paths['Solaris'] = ['/etc/syslog.conf']

        target.recognizor = /kern\.(\*|emerg|alert|crit|err|warn(ing)?|notice|info|debug)/
      end
    end
  end
end
