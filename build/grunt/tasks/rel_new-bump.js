
module.exports = function( grunt ) {

	grunt.registerTask( 'increment-version', function() {


		var semver = require('semver');
		var pkg = grunt.file.readJSON('package.json');

		var inc_version = semver.inc(
			pkg.version,
			'patch',
		);

		grunt.log.writeln( '\nIncrementing version to: ' + inc_version.magenta.bold );


		// Update current config so other tasks can use it
		var cfg = grunt.config( 'pkg' );
		cfg.version = inc_version;
		grunt.config( 'pkg', cfg );

		// update version in config file
		pkg.version = inc_version;
		grunt.file.write( 'package.json', JSON.stringify( pkg, null, 2 ) );
	});
}
