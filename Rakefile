require 'rubygems'

begin
  require 'bundler'
rescue LoadError => e
  STDERR.puts e.message
  STDERR.puts "Run `gem install bundler` to install Bundler."
  exit e.status_code
end

begin
  Bundler.setup(:development)
rescue Bundler::BundlerError => e
  STDERR.puts e.message
  STDERR.puts "Run `bundle install` to install missing gems"
  exit e.status_code
end

require 'rake'

require 'ore/tasks'
Ore::Tasks.new

require 'rspec/core/rake_task'
RSpec::Core::RakeTask.new
task :default => :spec

require 'yard'
YARD::Rake::YardocTask.new

lib_dir = File.expand_path(File.join(File.dirname(__FILE__),'lib'))
unless $LOAD_PATH.include?(lib_dir)
  $LOAD_PATH << lib_dir
end

require 'ronin/gen/generators/php/rpc_server'

namespace :php do
  namespace :rpc do
    deps = [
      'data/ronin/php/rpc',
      'data/ronin/gen/php/rpc/msgpack.php',
      'data/ronin/gen/php/rpc/service.php',
      'data/ronin/gen/php/rpc/console_service.php',
      'data/ronin/gen/php/rpc/shell_service.php',
      'data/ronin/gen/php/rpc/rpc_server.php',
      'data/ronin/gen/php/rpc/server.php.erb'
    ]

    ajax_deps = [
      'data/ronin/gen/php/rpc/ajax/css/layout.css',
      'data/ronin/gen/php/rpc/ajax/js/base64.js',
      'data/ronin/gen/php/rpc/ajax/js/msgpack.js',
      'data/ronin/gen/php/rpc/ajax/js/jquery.min.js',
      'data/ronin/gen/php/rpc/ajax/js/jquery-ui-personalized.min.js',
      'data/ronin/gen/php/rpc/ajax/js/jquery.terminal.js',
      'data/ronin/gen/php/rpc/ajax/js/php_rpc.js',
      'data/ronin/gen/php/rpc/ajax/js/ui.js'
    ]

    generator = Ronin::Gen::Generators::Php::RpcServer

    directory 'data/ronin/php/rpc'

    file 'data/ronin/php/rpc/server.php' => deps do |t|
      generator.generate({:no_ajax => true}, [t.name])
    end

    file 'data/ronin/php/rpc/server.ajax.php' => (deps + ajax_deps) do |t|
      generator.generate({}, [t.name])
    end

    desc 'Generates data files for Ronin::PHP::RPC'
    task :files => %w[
      data/ronin/php/rpc/server.php
      data/ronin/php/rpc/server.ajax.php
    ]
  end

  desc 'Generates data files for Ronin::PHP'
  task :files => 'php:rpc:files'
end

task :gemspec => 'php:files'
