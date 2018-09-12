/**
 * Gruntfile for Checkin
 */

module.exports = function (grunt) {
    // Project configuration.
    grunt.initConfig({
	pkg: grunt.file.readJSON('package.json'),
	uglify: {
	    options: {
		banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'
	    },
	    my_target: {
		files: {
		    'checkin.min.js': 'checkin.js',
		}
	    }
	},
	cssmin: {
	    target: {
		files: [{
			expand: true,
			cwd: 'external/leaflet/',
			src: ['*.css', '!*.min.css'],
			dest: 'external/leaflet/',
			ext: '.min.css'
		    }]
	    }
	},
	jshint: {
	    // define the files to lint
	    files: [
		'checkin.js'
	    ],

	    // configure JSHint (documented at http://www.jshint.com/docs/)
	    options: {
		// more options here if you want to override JSHint defaults
		globals: {
		},
		node: true,
		browser: true,
	    }
	}

    });

// Load the plugins
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    
// Tests
    grunt.registerTask('test', ['jshint']);
    
// Build language pack
    grunt.registerTask('build-lang', '', function(){
	
	const { execSync } = require('child_process');
	
	execSync('touch ./languages/source/known.pot'); // Make sure it exists, if we're going to remove (for broken builds)
	execSync('rm ./languages/source/known.pot'); // Remove existing
	
	execSync('find ./Idno -type f -regex ".*\.php" | php ./languages/processfile.php >> ./languages/source/known.pot'); // Build from idno core
	execSync('find ./templates -type f -regex ".*\.php" | php ./languages/processfile.php >> ./languages/source/known.pot'); // Build from templates
	
    });

// Default task(s).
    grunt.registerTask('default', ['uglify', 'cssmin']);
};