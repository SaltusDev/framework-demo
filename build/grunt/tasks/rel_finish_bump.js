module.exports = function( grunt ) {

	grunt.registerTask('finish-bump', function() {


		var pkg = grunt.file.readJSON('package.json');

		grunt.log.writeln( '\nAll done!'.magenta.bold );
		grunt.log.writeln( '\n' + '**'.red.bold + ' Don\'t forget to push the new git tag with: `git push <remote> ' + pkg.version + '` ' + '**'.red.bold );

	});

}