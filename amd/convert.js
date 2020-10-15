#!/usr/bin/env node
///
/// Very simple script to convert a normal JS module to a RequireJS-compatible
/// AMD module; intended for command-line usage, and integration into build 
/// systems.
///
/// Reads from stdin & writes to stdout; output can be piped to uglifyjs to 
/// minify/compress the code.
///
/// Examples:
///
/// For CommonJS-style modules, this is usually sufficient:
///   node convert.js < input.js > output.js
///
/// For standalone JS scripts that export a single object/namespace:
///   node convert.js -export:$objectName < input.js > output.js
///
/// If your standalone JS script requires other scripts:
///   node convert.js -dep:package-name:$packageVar -export:$objectName < input.js > output.js
///

// Parse command-line
var deps = [], parms = [], exports = [];
process.argv.slice(2).forEach(function(arg) {
  var split = arg.split(":");
  if(split[0] === "--export") {
    exports.push(split[1]);
  } else if(split[0] === "--dep") {
    deps.push(split[1]);
    parms.push(split[2]);
  }
});
if(deps.length) {
  deps = "['"+deps.join("', '")+"'], ";
}
if(parms.length) {
  parms = parms.join(", ")+", ";
}

// Write header
process.stdout.write("define("+deps+"function("+parms+"require, exports, module) {\n");

// Dump stdin -> stdout
process.stdin.resume();
process.stdin.setEncoding('utf8');
process.stdin.on('data', function(data) {
  process.stdout.write(data);
});

// Write footer
process.stdin.on('end', function() {
  if(exports.length == 1) {
    process.stdout.write("\treturn "+exports[0]+";\n");
  } else {
    exports.forEach(function(name) {
      process.stdout.write("\tmodule.exports."+name+" = "+name+";\n");
    });
  }
  process.stdout.write("});\n");
});