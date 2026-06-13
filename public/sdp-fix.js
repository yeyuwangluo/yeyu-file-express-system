// SDP兼容性修复工具
function fixSDPCompatibility(sdp) {
    if (!sdp || typeof sdp !== 'string') return sdp;
    
    // 移除不兼容的max-message-size行
    return sdp.split('\n').filter(line => {
        return !line.trim().startsWith('a=max-message-size:');
    }).join('\n');
}

// 修改原有的setRemoteDescription调用
const originalSetRemoteDescription = RTCPeerConnection.prototype.setRemoteDescription;
RTCPeerConnection.prototype.setRemoteDescription = async function(description) {
    if (typeof description === 'string') {
        description = new RTCSessionDescription({
            type: 'offer',
            sdp: fixSDPCompatibility(description)
        });
    } else if (description && description.sdp) {
        description = new RTCSessionDescription({
            type: description.type,
            sdp: fixSDPCompatibility(description.sdp)
        });
    }
    
    return originalSetRemoteDescription.call(this, description);
};

console.log('SDP compatibility fix loaded');

