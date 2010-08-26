source 'https://rubygems.org'

RONIN = 'http://github.com/ronin-ruby'

gem 'data_paths',	'~> 0.2.1'
gem 'ronin-support',	'~> 0.1.0', :git => "#{RONIN}/ronin-support.git"
gem 'ronin',		'~> 0.4.0', :git => "#{RONIN}/ronin.git"
gem 'ronin-scanners',	'~> 0.2.0', :git => "#{RONIN}/ronin-scanners.git"
gem 'ronin-web',	'~> 0.2.2', :git => "#{RONIN}/ronin-web.git"
gem 'ronin-exploits',	'~> 0.4.0', :git => "#{RONIN}/ronin-exploits.git"

group(:development) do
  gem 'bundler',	'~> 1.0.0'
  gem 'rake',		'~> 0.8.7'
  gem 'jeweler',	'~> 1.5.0', :git => 'http://github.com/technicalpickles/jeweler.git'
end

group(:doc) do
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
  gem 'ronin-gen',	'~> 0.3.0', :git => "#{RONIN}/ronin-gen.git"
end

gem 'rspec',	'~> 2.0.0.beta.20', :group => [:development, :test]
