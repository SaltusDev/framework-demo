/* global module require process */
module.exports = function(grunt) {
	var path = require( 'path' );


	// Project configuration
	var localenv = grunt.file.readJSON( 'grunt/config/localenv.json' );
	var localconfig = grunt.file.readJSON( 'grunt/config/config.json' );
	var localpkg = grunt.file.readJSON( 'package.json' );


	var grunt_config = {
		pkg: localpkg,
		cfg: localconfig,
		env: localenv,
	};

	grunt.initConfig( grunt_config );

	// load modules & tasks
	grunt.loadTasks( 'grunt/tasks' );

	// Default task.
	grunt.registerTask( 'default', ['build', 'bs-init', 'watch'] );

	// Browser Sync
	grunt.registerTask( 'bs',      [ 'bs-init', 'watch'] );

	// internationalization
	grunt.registerTask( 'i18n',    ['checktextdomain', 'makepot'] );

	// compiles all files for dev
	grunt.registerTask( 'build',   ['composer:update', 'notify', 'i18n'] );
	grunt.registerTask( 'dev',     ['build'] );

	// compiles all files for staging/production
	grunt.registerTask( 'prod',    ['build'] );

	// Release task
	grunt.registerTask( 'bump', [ 'increment-version', 'version', 'gittag', 'finish-bump' ]);
	grunt.registerTask( 'release', [ 'clean', 'new-release', 'shell:release', 'cssmin', 'copy', 'archive', 'finish-release' ]);

};
