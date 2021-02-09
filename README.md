# RedisLock
php implement redis lock

# usage

<?php
 
  $key ="aaa";
  $lk =new RedisLock();
  $lk->lock($key,0, 30);
  //process your business....
  
  $lk->unlock();
  
  //在实际的业务中，一般不用手动去lock unlock，这样业务代码容易出错，
  // 建议使用钩子，在开始和结束自动lock，unlock，对业务代码零侵入。
