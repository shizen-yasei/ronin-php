var PHP_RPC = {
  Request: {
    /*
     * Encodes a new PHP-RPC Request.
     */
    encode: function(method,args) {
      return Base64.encode(MessagePack.pack({
        'name': method,
        'arguments': args,
        'state': PHP_RPC.state
      }));
    }
  },

  Response: {
    // valid PHP-RPC Response types
    valid_types: {'error': true, 'return_value': true},

    // valid keys for PHP-RPC Responses
    valid_keys: {
      'error': ['message'],
      'return_value': ['state', 'output', 'return_value']
    },

    /*
     * Decodes a PHP-RPC Response.
     */
    decode: function(page) {
      var extractor = new RegExp("<rpc-response>(.*)<\/rpc-response>");
      var match = page.match(extractor);

      if (match == null || match[1] == null || match[1].length == 0)
      {
        throw "PHP-RPC Response missing";
      }

      var response = MessagePack.unpack(Base64.decode(match[1]));

      if (response == null || !(response instanceof Array))
      {
        throw "Invalid PHP-RPC Response";
      }

      if (response['type'] == null || !(response['type'] in PHP_RPC.Response.valid_types))
      {
        throw "Invalid PHP-RPC Response type";
      }

      var check_keys = PHP_RPC.Response.valid_keys[response['type']];

      for (var i=0; i<check_keys.length; i++)
      {
        if (response[check_keys[i]] == null)
        {
          throw "PHP-RPC Response is missing the " + check_keys[i] + " key";
        }
      }

      return response;
    }
  },

  // The URL of the Server to send requests to
  serverURL: window.location.href,

  // The HTTP Request method to use
  requestMethod: 'GET',

  // The State used by the PHP-RPC Client
  state: {},

  /*
   * Crafts a URL for a PHP-RPC Request.
   */
  callURL: function(method,args) {
    var url = PHP_RPC.serverURL;

    if (url.indexOf('?') == -1)
    {
      // if there is no '?' character, append it
      url += '?';
    }
    else if (url[url.length - 1] != '&')
    {
      url += '&';
    }

    var request = PHP_RPC.Request.encode(method,args);

    // insert the PHP-RPC Request message into the URL
    url += ('rpcrequest=' + encodeURIComponent(request));
    return url;
  },

  /*
   * Performs a PHP-RPC call.
   */
  call: function(method,args,callback) {
    var url = PHP_RPC.callURL(method,args);

    jQuery.ajax({
      url: url,
      type: PHP_RPC.requestMethod,
      success: function(data) {
        var response = PHP_RPC.Response.decode(data);

        PHP_RPC.state = response['state'];
        callback(response);
      }
    });
  },

  /*
   * Performs a PHP-RPC call to a specific service.
   */
  callService: function(service,method,args,callback) {
    PHP_RPC.call(service + '.' + method,args,callback);
  }
};
