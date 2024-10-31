 <?php ?>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<h1>Link Post bài tự động</h1>
<div class="container">
    <h1>Post bài từ động</h1>
    <form method="post" class="form-post-content">
        <div class="col-md-6"> Đăng bài <b><?php echo $itemPage->title; ?></b></div>
        <div class="right text-right col-md-6">
            <button>Lưu bài</button>
        </div>
        <div>Title <input name="title" /> </div>
        <div>Description <textarea name="introtext" cols="100" rows="3"></textarea></div>
        <div>Content <textarea name="fulltext" cols="100" rows="30"></textarea></div>
        <div>Created <input name="cdate" /></div>
        <div>Keywords <textarea name="meta_key" cols="100" rows="3"></textarea></div>
        <div>Thumbnail <input name="thumbnail" /></div>
        <div>Khóa an toàn <input name="secret_key" /></div>
        <div>Link <input name="link-source" /></div>
    </form>
</div>
<style>
    .form-group {
        margin-bottom: 5px;
    }
</style>
<script>
    $(window).ready(function(){
        $(".btn-save-post").click(function(){
           $(".form-post-content") .submit();
        });
    });
</script>