# -*- ruby -*-

require 'rubygems'
require 'hoe'
require 'hoe/signing'
require './tasks/spec.rb'
require './tasks/yard.rb'
require './tasks/static.rb'

Hoe.spec('ronin-php') do
  self.rubyforge_name = 'ronin'
  self.developer('Postmodern','postmodern.mod3@gmail.com')
  self.remote_rdoc_dir = 'docs/ronin-php'
  self.extra_deps = [
    ['ronin', '>=0.3.0'],
    ['ronin-web', '>=0.2.0']
  ]

  self.extra_dev_deps = [
    ['rspec', '>=1.1.12'],
    ['yard', '>=0.2.3.5'],
    ['cssmin', '>=1.0.2'],
    ['jsmin', '>=1.0.1'],
    ['hpricot', '>=0.8.1']
  ]

  self.spec_extras = {:has_rdoc => 'yard'}
end

# vim: syntax=Ruby
