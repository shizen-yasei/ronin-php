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

module Ronin
  module PHP
    class LFI
      class Target

        # Hash of OS specific paths for the target
        attr_reader :paths

        # Hash of patterns to recognize the target by
        attr_accessor :recognizor

        # Hash of extractor rules
        attr_reader :extractors

        #
        # Creates a new Path object with the specified _path_ and _pattern_.
        #
        def initialize(&block)
          @paths = Hash.new { |hash,key| hash[key] = [] }

          @recognizor = nil
          @extractors = {}

          block.call(self) if block
        end

        #
        # Returns the supported OSes.
        #
        def oses
          @paths.keys
        end

        #
        # Returns all the paths of the target.
        #
        def all_paths
          @paths.values.flatten.uniq
        end

        #
        # Returns the paths for the target commonly found on the specified _os_.
        #
        def paths_for(os)
          @paths[os]
        end

        #
        # Iterates over each path passing each one to the specified _block_.
        #
        def each_path(&block)
          @paths.each_value do |os_paths|
            os_paths.each(&block)
          end
        end

        #
        # Returns +true+ if the specified _body_ has the path included in
        # it, returns +false+ otherwise.
        #
        def included_in?(body)
          if @recognizor
            return !((body =~ @recognizor).nil?)
          else
            return false
          end
        end

        #
        # Add an extraction rule with the specified _name_ and the
        # specified _pattern_.
        #
        def extract(name,pattern)
          @extractors[name] = pattern
        end

        #
        # Extracts data from the specified _body_ of HTML.
        #
        def extract_from(body)
          data = {}

          @extractors.each do |name,pattern|
            match = pattern.match(body)

            if match
              if match.length > 2
                data[name] = match[1..-1]
              elsif match.length == 2
                data[name] = match[1]
              else
                data[name] = match[0]
              end
            end
          end

          return data
        end

        #
        # Returns all Target categories.
        #
        def Target.categories
          @@categories ||= Hash.new { |hash,key| hash[key] = [] }
        end

        #
        # Returns +true+ if there is a category with the specified _name_,
        # returns +false+ otherwise.
        #
        def Target.has_category?(name)
          Target.categories.has_key?(name)
        end

        #
        # Returns the targets within the category with the specified _name_.
        #
        def Target.category(name)
          Target.categories[name]
        end

        #
        # Returns all targets.
        #
        def Target.all
          Target.categories.values.flatten
        end

        #
        # Iterates over all targets, passing each to the given _block_.
        #
        def Target.each(&block)
          Target.categories.each_value do |targets|
            targets.each(&block)
          end
        end

        #
        # Defines a new target within the test category of targets, used
        # for testing LFI.
        #
        def Target.test(&block)
          Target.define(:test,&block)
        end

        #
        # Defines a new target within the config category of configuration
        # file targets.
        #
        def Target.config(&block)
          Target.define(:config,&block)
        end

        #
        # Defines a new target within the log category of log file targets.
        #
        def Target.log(&block)
          Target.define(:log,&block)
        end

        #
        # Returns the targeted files used in testing for LFI.
        #
        def Target.tests
          Target.category(:test)
        end

        #
        # Returns the targeted configuration files.
        #
        def Target.configs
          Target.category(:config)
        end

        #
        # Returns the targeted log files.
        #
        def Target.logs
          Target.category(:log)
        end

        #
        # Returns all targets for the specified _os_.
        #
        def Target.targets_for(os)
          Target.each do |target|
            if target.oses.include?(os)
              return target
            end
          end
        end

        #
        # Returns all targets with extractors.
        #
        def Target.with_extractors
          targets = []

          Target.each do |target|
            unless target.extractors.empty?
              targets << target
            end
          end

          return targets
        end

        #
        # Returns all targets with the specified file _name_.
        #
        def Target.with_file(name)
          Target.each do |target|
            target.each_path do |path|
              return target if path =~ /#{name}$/
            end
          end
        end

        protected

        #
        # Defines a new Target in the specified category _name_.
        #
        def self.define(name,&block)
          new_target = Target.new(&block)

          Target.categories[name] << new_target
          return new_target
        end

      end
    end
  end
end
