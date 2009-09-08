require 'ronin/rpc/php/rfi'

require 'spec_helper'

describe PHP::RFI do
  it "should have a valid RPC_SCRIPT URL" do
    response = Net.http_get(:url => PHP::RFI::RPC_SCRIPT)

    response.code.to_i.should == 200
    response.body.should_not be_empty
  end

  it "should have a default rpc_script URL" do
    PHP::RFI.rpc_script.should == PHP::RFI::RPC_SCRIPT
  end

  it "should allow configuration of the rpc_script URL" do
    new_url = 'http://www.example.com/rpc.php'

    PHP::RFI.rpc_script = new_url
    PHP::RFI.rpc_script.should == new_url
  end

  after(:all) do
    PHP::RFI.rpc_script = nil
  end
end
