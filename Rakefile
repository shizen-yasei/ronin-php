require 'rubygems'
require 'rake'
require './lib/ronin/php/version.rb'

begin
  require 'jeweler'
  Jeweler::Tasks.new do |gem|
    gem.name = 'ronin-php'
    gem.version = Ronin::PHP::VERSION
    gem.licenses = ['GPL-2']
    gem.summary = %Q{A Ruby library for Ronin that provides support for PHP related security tasks.}
    gem.description = %Q{Ronin PHP is a Ruby library for Ronin that provides support for PHP related security tasks.}
    gem.email = 'postmodern.mod3@gmail.com'
    gem.homepage = 'http://github.com/ronin-ruby/ronin-php'
    gem.authors = ['Postmodern']
    gem.add_dependency 'cssmin', '~> 1.0.2'
    gem.add_dependency 'jsmin', '~> 1.0.1'
    gem.add_dependency 'ronin', '~> 0.4.0'
    gem.add_dependency 'ronin-web', '~> 0.2.2'
    gem.add_dependency 'ronin-exploits', '~> 0.3.2'
    gem.add_development_dependency 'rspec', '~> 1.3.0'
    gem.add_development_dependency 'yard', '~> 0.5.3'
    gem.has_rdoc = 'yard'

    gem.files.include %w{
      static/ronin/php/rpc/server.php
      static/ronin/php/rpc/server.min.php
    }
  end
rescue LoadError
  puts "Jeweler (or a dependency) not available. Install it with: gem install jeweler"
end

require 'spec/rake/spectask'
Spec::Rake::SpecTask.new(:spec) do |spec|
  spec.libs += ['lib', 'spec']
  spec.spec_files = FileList['spec/**/*_spec.rb']
  spec.spec_opts = ['--options', '.specopts']
end

task :spec => :check_dependencies
task :default => :spec

begin
  require 'yard'

  YARD::Rake::YardocTask.new
rescue LoadError
  task :yard do
    abort "YARD is not available. In order to run yard, you must: gem install yard"
  end
end

lib_dir = File.expand_path(File.join(File.dirname(__FILE__),'lib'))
unless $LOAD_PATH.include?(lib_dir)
  $LOAD_PATH << lib_dir
end

require 'ronin/gen/generators/php/rpc_server'

namespace :php do
  namespace :rpc do
    deps = [
      'static/ronin/php/rpc',
      'static/ronin/gen/php/rpc/service.php',
      'static/ronin/gen/php/rpc/console_service.php',
      'static/ronin/gen/php/rpc/shell_service.php',
      'static/ronin/gen/php/rpc/rpc_server.php',
      'static/ronin/gen/php/rpc/server.php.erb'
    ]

    ajax_deps = [
      'static/ronin/gen/php/rpc/ajax/css/layout.css',
      'static/ronin/gen/php/rpc/ajax/js/base64.js',
      'static/ronin/gen/php/rpc/ajax/js/jquery.min.js',
      'static/ronin/gen/php/rpc/ajax/js/jquery-ui-personalized.min.js',
      'static/ronin/gen/php/rpc/ajax/js/jquery.phprpc.js',
      'static/ronin/gen/php/rpc/ajax/js/jquery.terminal.js',
      'static/ronin/gen/php/rpc/ajax/js/ui.js',
    ]

    generator = Ronin::Gen::Generators::Php::RpcServer

    directory 'static/ronin/php/rpc'

    file 'static/ronin/php/rpc/server.php' => deps do |t|
      generator.generate({:no_ajax => true}, [t.name])
    end

    file 'static/ronin/php/rpc/server.ajax.php' => (deps + ajax_deps) do |t|
      generator.generate({}, [t.name])
    end
  end
end

task :gemspec => [
  'static/ronin/php/rpc/server.php',
  'static/ronin/php/rpc/server.ajax.php'
]
