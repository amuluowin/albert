关于
====

本项目为基于Yii2+Swoole的Composer组件包

开始
---

执行
```
php composer.phar require --prefer-dist albertwxy/yii2-swoole
```

或者在composer.json中添加

```
"albertwxy/yii2-swoole":"dev-master"
```

主要功能
------

* `Http,Websocket,Tcp,Task`服务器
* `协程Mysql,Redis,Tcp`以及连接池
* `协程HttpClient`
* 多进程定时任务
* 多进程消息队列
* 共享内存缓存
* `inotify`自动reload
* `systemd`管理服务
* 扩展`ORM`,更灵活
* 扩展`restful`,更灵活
* 协程文件IO
* 扩展`日志`和`DEBUG`组件使用协程文件IO
* `shmcache`共享内存缓存
* `swoole_table`共享内存缓存

参考使用demo
-----------

* https://gitee.com/laoyaosu/yiisw.git
