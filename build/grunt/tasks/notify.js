module.exports = function(grunt) {

	// Minify js
	grunt.config('notify', {
		build: {
			options: {
				title:   'Build complete',
				message: 'All assets are ready for merge and deploy'
			}
		}
	});
	grunt.loadNpmTasks('grunt-notify');

};

