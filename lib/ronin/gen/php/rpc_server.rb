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

require 'ronin/gen/php/config'
require 'ronin/gen/file_generator'

require 'cssmin'
require 'jsmin'

module Ronin
  module Gen
    module PHP
      class RPCServer < FileGenerator

        SERVER_FILE = File.join('ronin','gen','php','rpc','server.php.erb')

        class_option :no_ajax, :type => :boolean
        class_option :no_cssmin, :type => :boolean
        class_option :no_jsmin, :type => :boolean
        class_option :inline, :type => :array, :default => []

        def generate
          template SERVER_FILE, self.path
        end

        protected

        def css_min(css)
          css = CSSMin.minify(css) unless options.no_cssmin?

          return "<style type=\"text/css\">#{css}</style>"
        end

        def js_min(js)
          js = JSMin.minify(js) unless options.no_jsmin?

          return "<script type=\"text/javascript\">#{js}</script>"
        end

      end
    end
  end
end
