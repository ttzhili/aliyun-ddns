# 利用官方SDK实现相关功能，只具备添加、更新

# 实现流程：
 1. 初始化相关赋值.
 2. 创建SDK客户端.
 3. 查询RR + Domain.
 4. 不存在则添加，存在则更新.
 5. 完成.
.
# 运行：
 php this.php $accessKeyId $accessKeySecret $domain $RR $type.

# 参数：
| 参数 | 注释
| :------- | :---------------:        |
| 1. accessKeyId      | RAM用户                      |
| 2. accessKeySecret  | RAM密钥                      |
| 3. domain           | 域名：xxx.com、xxx.cn等       |
| 4. RR               | 解析名 如：@、www            |
| 5. type             | 解析内容方式：A、AAAA         |


# 阿里云RAM用户文档：
https://help.aliyun.com/document_detail/53045.html?spm=a2c4g.324629.0.0.348f7f80k5BZTc
