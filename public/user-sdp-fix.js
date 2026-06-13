// 局域网互传SDP兼容性修复
// 将此脚本复制到浏览器控制台中执行

(function() {
    'use strict';
    
    console.log('正在应用SDP兼容性修复...');
    
    // SDP清理函数
    function fixSDP(sdp) {
        if (!sdp || typeof sdp !== 'string') return sdp;
        
        // 移除不兼容的max-message-size行
        return sdp.split('\n').filter(line => {
            return !line.trim().startsWith('a=max-message-size:');
        }).join('\n');
    }
    
    // 拦截setRemoteDescription
    const originalSetRemoteDescription = RTCPeerConnection.prototype.setRemoteDescription;
    RTCPeerConnection.prototype.setRemoteDescription = async function(description) {
        try {
            if (typeof description === 'string') {
                description = new RTCSessionDescription({
                    type: 'offer',
                    sdp: fixSDP(description)
                });
            } else if (description && description.sdp) {
                description = new RTCSessionDescription({
                    type: description.type,
                    sdp: fixSDP(description.sdp)
                });
            }
            
            console.log('SDP修复已应用');
            return originalSetRemoteDescription.call(this, description);
        } catch (error) {
            console.error('SDP修复失败:', error);
            throw error;
        }
    };
    
    console.log('✓ SDP兼容性修复已应用');
    console.log('现在可以尝试重新建立局域网互传连接');
})();

