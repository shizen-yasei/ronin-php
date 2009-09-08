require 'ronin/php/version'

require 'spec_helper'

describe PHP do
  it "should have a version" do
    PHP.const_defined?('VERSION').should == true
  end
end
