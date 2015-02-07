// Plugins
var gulp = require('gulp'),
	 watch = require('gulp-watch'),
	 sass = require('gulp-ruby-sass');

// Styles
gulp.task('styles', function() {
	gulp.src('wp-forms-api.scss')
		.pipe(sass({style: 'compressed'}))
		.pipe(gulp.dest('wp-forms-api'));
});

gulp.task('watch', function() {
	// Watch the sass files
	gulp.watch('**/*.scss', ['styles']);
});

// Run all tasks by default
gulp.task('default', ['styles']);

