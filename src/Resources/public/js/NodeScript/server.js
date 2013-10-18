var io = require("socket.io");
var http = require("http");
var fs = require("fs");
var app = http.createServer(function(req, res) {
    fs.readFile("./server.html", "utf-8", function(error, content)
    {
        res.writeHead(200, {'Content-Type' : 'text/html'});
        res.end(content);
    })
}).listen(8765);

var sessionManagement = function()
{
    this.users = [];

    this.addUser = function(sessionId, socket)
    {
        if(this.users[sessionId] === undefined) this.users[sessionId] = new Array();
        this.delUser(socket);
        this.users[sessionId].push(socket);
    }

    this.isUser = function(sessionId)
    {
        return (typeof this.users[sessionId] != "undefined");
    }

    this.getUser = function(sessionId)
    {
        if(!this.isUser(sessionId)) return null;

        return this.users[sessionId];
    }

    this.delUser = function(socket)
    {
        for(var sessionId in this.users)
        {
            var indexToRemove = this.users[sessionId].indexOf(socket);
            if(indexToRemove != -1)
            {
                delete this.users[sessionId][indexToRemove];
                return sessionId;
            }
        }

        return false;
    };
};


var sessionManager = new sessionManagement();

io = io.listen(app,{ log: false });

io.sockets.on('connection', function(socket){
    // List of events proxy



        socket.on('proxyevent', function(parameters) {
            if(!Array.isArray(parameters.clients) && parameters.clients == "all")
            {
                socket.broadcast.emit("proxyevent", { "name" : parameters.name, "parameters" : parameters.parameters });
            }
            else
            {
                for(var i in parameters.clients)
                {
                    var sessionId = parameters.clients[i];

                    var recepters = sessionManager.getUser(sessionId);

                    for(var indexRecepted in recepters)
                    {
                        var recepter = recepters[indexRecepted];

                        if(recepter !== null)
                        {
                            console.log("EMIT ::: " + sessionId);

                            recepter.emit("proxyevent", { "name" : parameters.name, "parameters" : parameters.parameters });
                        }
                        else
                        {
                            console.log("NO EMIT ::: " + sessionId);
                        }
                    }
                }
            }
        });

        socket.on('auth', function(parameters)
        {
            console.log("AUTH ::: " + parameters.sessionId);

            sessionManager.addUser(parameters.sessionId, socket);
        });

        socket.on('disconnect', function () {
            console.log("DEAUTH : TENTATIVE");

            var address = socket.handshake.address;
            
            if(address.address == "127.0.0.1") return;

            var sessionId = sessionManager.delUser(socket);

            if(sessionId !== false)
            {
                console.log("DEATUH :: " + sessionId);
            }
        });

});


