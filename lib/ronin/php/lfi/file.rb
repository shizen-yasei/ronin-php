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

module Ronin
  module PHP
    class LFI
      class File < StringIO

        # Path to the file
        attr_reader :path

        #
        # Creates a new Inclusion with the specified _path_ and response
        # _body_.
        #
        def initialize(path,body)
          super(body)

          @path = path
        end

        #
        # Returns the contents of the File in String form.
        #
        def contents
          string
        end

        #
        # See contents.
        #
        def to_s
          contents
        end

        def inspect
          "#<#{self.class}:#{@path}>"
        end

        #
        # Saves the body to specified _destination_, returns the
        # _destination_.
        #
        def save(destination)
          File.open(destination,'w') do |dest|
            dest.write(string)
          end

          return destination
        end

        def mirror(base)
          dest = File.join(base,@path)
          dest_dir = File.dirname(dest)

          unless File.directory?(dest_dir)
            FileUtils.mkdir_p(dest_dir)
          end

          return save(dest)
        end

      end
    end
  end
end
