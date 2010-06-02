source 'http://rubygems.org'
ronin_ruby = 'git://github.com/ronin-ruby'

group :runtime do
  gem 'data_paths',	'~> 0.2.1'
  gem 'ronin-support',	'~> 0.1.0', :git => "#{ronin_ruby}/ronin-support.git"
  gem 'ronin',		'~> 0.4.0', :git => "#{ronin_ruby}/ronin.git"
  gem 'ronin-scanners',	'~> 0.2.0', :git => "#{ronin_ruby}/ronin-scanners.git"
  gem 'ronin-web',	'~> 0.2.2', :git => "#{ronin_ruby}/ronin-web.git"
  gem 'ronin-exploits',	'~> 0.4.0', :git => "#{ronin_ruby}/ronin-exploits.git"
end

group :development do
  gem 'bundler',	'~> 0.9.19'
  gem 'rake',		'~> 0.8.7'
  gem 'jeweler',	'~> 1.4.0', :git => 'git://github.com/technicalpickles/jeweler.git'
end

group :doc do
  case RUBY_PLATFORM
  when 'java'
    gem 'maruku',	'~> 0.6.0'
  else
    gem 'rdiscount',	'~> 1.6.3'
  end

  gem 'yard',		'~> 0.5.3'
end

group(:development, :runtime) do
  gem 'cssmin',		'~> 1.0.2'
  gem 'jsmin',		'~> 1.0.1'
  gem 'ronin-gen',	'~> 0.3.0', :git => "#{ronin_ruby}/ronin-gen.git"
end

gem 'rspec',		'~> 1.3.0', :group => [:development, :test]
