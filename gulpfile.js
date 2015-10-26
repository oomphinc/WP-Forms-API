// Plugins
var gulp = require('gulp'),
	 watch = require('gulp-watch'),
	 sass = require('gulp-ruby-sass');

// Styles
gulp.task('styles', function() {
	gulp.src('sass/wp-forms-api.scss')
		.pipe(sass({style: 'compressed'}))
		.pipe(gulp.dest('src/'));
});

gulp.task('watch', function() {
	// Watch the sass files
	gulp.watch('sass/**/*.scss', ['styles']);
});

// Run all tasks by default
gulp.task('default', ['styles']);

