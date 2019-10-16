module.exports = function( grunt ) {

	grunt.registerTask('new-release', function() {

		var path = require('path');

		var pkg = grunt.file.readJSON('package.json');

		grunt.log.writeln( '\nCreating new release: ' + pkg.version.magenta.bold );

		var dist_dir = path.normalize(__dirname + "/../../dist/" + pkg.name );
		var release_dir = path.normalize(__dirname + "/../../release/" );

		grunt.log.writeln( '\nCreating new dist directory in: ' + dist_dir );
		grunt.log.writeln( '\nCreating new release directory in: ' + release_dir );
		grunt.file.mkdir( dist_dir );
		grunt.file.mkdir( release_dir );

	});

}