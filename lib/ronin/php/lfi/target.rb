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
        # Creates a new Target object.
        #
        # @yield [target]
        #   If a block is given, it will be passed the newly created
        #   Target object.
        #
        def initialize(&block)
          @paths = Hash.new { |hash,key| hash[key] = [] }

          @recognizor = nil
          @extractors = {}

          block.call(self) if block
        end

        #
        # The supported OSes.
        #
        # @return [Array]
        #   OSes that the targeted file can be found on.
        #
        def oses
          @paths.keys
        end

        #
        # All the paths of the target.
        #
        # @return [Array]
        #   Paths of the targetted file.
        #
        def all_paths
          @paths.values.flatten.uniq
        end

        #
        # Finds the paths for the targetted file commonly found on a given
        # OS.
        #
        # @param [String] os
        #   The OS to search for.
        #
        # @return [String]
        #   The path that the targetted file can be found at on the given
        #   OS.
        #
        def paths_for(os)
          @paths[os]
        end

        #
        # Iterates over each path.
        #
        # @yield [path]
        #   A given block will be passed each known path of the targetted
        #   file.
        #
        # @yieldparam [String] path
        #   A path that the targetted file is known to reside at.
        #
        def each_path(&block)
          @paths.each_value do |os_paths|
            os_paths.each(&block)
          end
        end

        #
        # Determines if the given body of text contains the targetted file.
        #
        # @param [String] body
        #   The body of text to examine.
        #
        # @return [Boolean]
        #   Specifies whether the a given body of text has the targetted
        #   file included in it.
        #
        def included_in?(body)
          if @recognizor
            return !((body =~ @recognizor).nil?)
          else
            return false
          end
        end

        #
        # Add an extraction rule to the targetted file.
        #
        # @param [Symbol] name
        #   The name of the rule.
        #
        # @param [Regexp] pattern
        #   The pattern to extract.
        #
        def extract(name,pattern)
          @extractors[name] = pattern
        end

        #
        # Extracts data from a given body of text.
        #
        # @param [String] body
        #   The body of text to extract data from.
        #
        # @return [Hash]
        #   The extracted data, grouped by the name of the extract rule
        #   and the extracted data.
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
        # All Target categories.
        #
        # @return [Hash]
        #   All categories of targetted files.
        #
        def Target.categories
          @@categories ||= Hash.new { |hash,key| hash[key] = [] }
        end

        #
        # Determines if any targetted files have been defined in a given
        # category.
        #
        # @param [Symbol] name
        #   The category to search for.
        #
        # @return [Boolean]
        #   Specifies whether there is a category with the given name.
        #
        def Target.has_category?(name)
          Target.categories.has_key?(name)
        end

        #
        # Finds the targets within the category with a given name.
        #
        # @param [Symbol] name
        #   The category to search for.
        #
        # @return [Array<Target>]
        #   The targetted files within the category.
        #
        def Target.category(name)
          Target.categories[name]
        end

        #
        # All targets.
        #
        # @return [Array<Target>]
        #   All targetted files.
        #
        def Target.all
          Target.categories.values.flatten
        end

        #
        # Iterates over all targets.
        #
        # @yield [target]
        #   The given block will be passed each registered targetted file.
        #
        # @yieldparam [Target] target
        #   A targetted file.
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
        # The targeted files used in testing for LFI.
        #
        # @return [Array<Target>]
        #   The targetted files used to identify LFI vulnerabilities.
        #
        def Target.tests
          Target.category(:test)
        end

        #
        # The targeted configuration files.
        #
        # @return [Array<Target>]
        #   The targetted configuration files.
        #
        def Target.configs
          Target.category(:config)
        end

        #
        # The targeted log files.
        #
        # @return [Array<Target>]
        #   The targetted log files.
        #
        def Target.logs
          Target.category(:log)
        end

        #
        # All targets for a given OS.
        #
        # @param [String] os
        #   The OS to find targetted files for.
        #
        # @return [Array<Target>]
        #   All targetted files for the given OS.
        #
        def Target.targets_for(os)
          Target.each do |target|
            if target.oses.include?(os)
              return target
            end
          end
        end

        #
        # All targets with extractors.
        #
        # @return [Array<Target>]
        #   All targetted files with data extraction rules.
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
        # All targets with a matching file-name.
        #
        # @param [String] name
        #   The file-name to search for.
        #
        # @return [Array<Target>]
        #   The targetted files with the matching file-name.
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
        # Defines a new targetted file in a given category.
        #
        # @param [Symbol] name
        #   The category to define the targetted file within.
        #
        # @return [Target]
        #   The newly defined targetted file.
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
