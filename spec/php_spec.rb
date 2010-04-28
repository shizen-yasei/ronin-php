require 'spec_helper'
require 'ronin/php/version'

describe PHP do
  it "should have a version" do
    PHP.const_defined?('VERSION').should == true
  end
end
