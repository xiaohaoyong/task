#!/usr/bin/env python
# encoding: utf-8
import redis,os,commands,time,ConfigParser;

#获取全部配置文件
if(os.path.exists('XYWYSRV_CONFIG')):
	all_the_text = open('XYWYSRV_CONFIG').read()
	all_the_text = "[config]\n"+all_the_text
	file_object = open('XYWYSRV_CONFIG_INI', 'w')
	file_object.write(all_the_text)
	file_object.close()

#获取redis 配置信息xxxxxxxx
cf=ConfigParser.ConfigParser()
cf.read('XYWYSRV_CONFIG_INI')
secs = cf.get('config','xywysrv_server_name').strip('"');
REDIS_HOST = '10.20.9.12'
REDIS_PORT = 6379
print('t')

#实例化主库
r=redis.StrictRedis(host=REDIS_HOST,port=REDIS_PORT,db=0)
key='yimai:list:task'
#开始获取任务队列
while True:
	cmd="ps -ef | grep task_processor.php | wc -l"
	f=int(commands.getoutput(cmd).strip())
	fnum=f-2
	print(time.ctime()+"==执行中的进程数："+str(fnum))
	if(fnum>10):
		print(time.ctime()+"==sleep")
		time.sleep(30)
		continue
	print(time.ctime()+"==等待任务")
	var=r.blpop('yimai:list:task')
	arr=var[1].split('|@')
	print(arr[0])
	if(arr[0]):
		pro=arr[0].split('|%')
		if(len(pro)>1):
			rkey=key+":"+pro[0]+pro[1]
			print(arr[1])
			re=r.rpush(rkey,arr[1])
			print(time.ctime()+"==执行任务")
			cmd="/usr/local/xywy/php/bin/php task/task_processor.php "+pro[0]+" "+pro[1] +" 1>/dev/null 2>/dev/null&"
			re=os.system(cmd)
			print(re)
