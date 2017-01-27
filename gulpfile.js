var gulp = require('gulp');

gulp.task('move', function () {
    gulp.src('web/**/*')
        .pipe(gulp.dest('../../../../public/extensions/local/bacboslab/menueditor/'));
});


gulp.task('default', function () {
    gulp.watch('web/**/*', ['move']);
});

